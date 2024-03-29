<?php

namespace Webbmaffian\ORM\Abstracts;

use Webbmaffian\ORM\Helpers\Helper;
use Webbmaffian\ORM\Helpers\Database_Exception;

abstract class Sql {
	const VARIABLE_REGEX = '/(?<!\:)\:(\@?[a-z0-9_]+)/i';
	const TRUE_VALUE = 1;
	const FALSE_VALUE = 0;
	const NULL_VALUE = 'NULL';

	protected $instance = null;
	protected $schema = null;
	protected $is_transaction = false;
	protected $savepoint_increment = 0;
	static protected $store_timezone = null;


	public function __construct($args = array()) {
		if(empty($args)) {
			throw new Database_Exception('Missing arguments.');
		}

		if(!is_array($args)) {
			throw new Database_Exception('Arguments must be an array.');
		}

		$this->setup_instance($args);
	}


	abstract protected function setup_instance($args);


	public function get_schema() {
		return $this->schema;
	}


	public function is_transaction() {
		return $this->is_transaction;
	}


	public function transaction($callback, $savepoint_fallback = true) {
		if(!is_callable($callback)) {
			throw new Database_Exception('Invalid transaction callback.');
		}

		if($savepoint_fallback) {
			$savepoint_fallback = method_exists($this, 'add_savepoint');
		}

		$already_in_transaction = $this->is_transaction();

		try {
			if(!$already_in_transaction) $this->start_transaction();
			elseif($savepoint_fallback) $this->add_savepoint();

			$return = call_user_func($callback);

			if(!$already_in_transaction) $this->end_transaction();
			elseif($savepoint_fallback) $this->release_savepoint();

			return $return;
		}
		catch(\Exception $e) {
			if(!$already_in_transaction) $this->rollback();
			elseif($savepoint_fallback) $this->rollback_savepoint();

			throw $e;
		}
	}


	abstract public function start_transaction();
	abstract public function end_transaction();
	abstract public function rollback();
	abstract public function add_savepoint($name = null);
	abstract public function release_savepoint($name = null);
	abstract public function rollback_savepoint($name = null);
	abstract public function escape_string($string, $add_quotes = false);
	abstract public function query_params($query, $args);
	abstract protected function get_real_upsert_query($table, $param_keys = array(), $param_values = array(), $keys_to_update = array(), $auto_increment = null, $unique_keys = array());
	abstract protected function last_error();
	abstract protected function run_query($query);


	public function query() {
		$args = func_get_args();
		$query = array_shift($args);

		if(!is_string($query)) {
			throw new Database_Exception('Query must be a string.');
		}

		if(!empty($args)) {
			if(count($args) === 1) {
				$args = is_array($args[0]) ? $args[0] : array($args[0]);
			}

			return $this->query_params($query, $args);
		}

		return $this->run_query($query);
	}


	public function get_result() {
		$args = func_get_args();
		$result = call_user_func_array(array($this, 'query'), $args);

		return $result->fetch_all();
	}
	
	
	public function get_value() {
		$args = func_get_args();
		$result = call_user_func_array(array($this, 'query'), $args);
		
		return $result->fetch_value();
	}
	
	
	public function get_column() {
		$args = func_get_args();
		$result = call_user_func_array(array($this, 'query'), $args);
		
		return $result->fetch_column();
	}
	
	
	public function get_row() {
		$args = func_get_args();
		$result = call_user_func_array(array($this, 'query'), $args);
		
		return $result->fetch_assoc();
	}


	abstract public function get_last_id();


	protected function get_param_string($params = array(), $delimiter = ', ') {
		return urldecode(http_build_query($params, '', $delimiter));
	}


	protected function format_values($params = array(), $quotes = true) {
		foreach($params as $key => $value) {
			if(is_array($value)) {
				$value = json_encode($value);
			}
			elseif($value instanceof \DateTime) {
				if(self::$store_timezone) {
					$value->setTimezone(self::$store_timezone);
				}

				$value = $value->format('Y-m-d H:i:s');
			}
			
			if(Helper::is_numeric($value)) {
				$params[$key] = (float)$value;
			}
			elseif(is_string($value)) {
				$params[$key] = $this->escape_string(trim($value), $quotes);
			}
			elseif(is_bool($value)) {
				$params[$key] = $value ? static::TRUE_VALUE : static::FALSE_VALUE;
			}
			elseif(is_null($value)) {
				$params[$key] = static::NULL_VALUE;
			}
		}
		
		return $params;
	}


	static public function set_store_timezone($timezone) {
		if(!$timezone instanceof \DateTimeZone) {
			throw new Database_Exception('Invalid DateTimeZone object.');
		}

		self::$store_timezone = $timezone;
	}


	/**
	 * Converts associative parametered queries like:
	 * $query = SELECT * FROM :@table WHERE name = :name AND something = :somewhat OR another_name = :name
	 * $params = array('table' => 'shops', 'name' => 'A Name', 'somewhat' => 'Some data')
	 * 
	 * ... to:
	 * $query = SELECT * FROM shops WHERE name = ? AND something = ? OR another_name = ?
	 * $params = array('A Name', 'Some data', 'A name')
	 * 
	 * ... in order to run mysqli prepared statements
	 */
	static protected function convert_assoc($query = '', $params = array()) {
		list($new_query, $mappings) = static::convert_query($query, $params);

		$params = static::sort_params($params, $mappings);

		return array($new_query, $params);
	}


	abstract static public function sort_params($params = array(), $mappings = array());
	abstract static public function convert_query($query = '', &$params = array());


	public function get_instance() {
		return $this->instance;
	}


	public function get_column_names($table) {
		$query = 'SELECT column_name FROM information_schema.columns WHERE table_schema = :schema_name AND table_name = :table_name';

		return $this->get_column($query, array(
			'schema_name' => $this->schema,
			'table_name' => $table
		));
	}


	public function upsert($table, $params = array(), $unique_keys = array(), $dont_update_keys = array(), $auto_increment = null) {
		$this->query($this->get_upsert_query($table, $params, $unique_keys, $dont_update_keys, $auto_increment));

		return true;
	}


	protected function get_upsert_query($table, $params = array(), $unique_keys = array(), $dont_update_keys = array(), $auto_increment = null, $quotes = true) {
		$params = $this->format_values($params, $quotes);
		
		if(!is_array($unique_keys)) {
			$unique_keys = array($unique_keys);
		}

		if(!is_array($dont_update_keys)) {
			$dont_update_keys = array($dont_update_keys);
		}
		
		$param_keys = array_keys($params);
		$param_values = array_values($params);

		// Append unique keys, as they shouldn't get updated
		$dont_update_keys = array_unique(array_merge($dont_update_keys, $unique_keys));

		// Find out which keys should be updated
		$keys_to_update = array_diff($param_keys, $dont_update_keys);

		if(empty($keys_to_update)) {
			throw new Database_Exception('No keys to update during upsert');
		}

		return $this->get_real_upsert_query($table, $param_keys, $param_values, $keys_to_update, $auto_increment, $unique_keys);
	}
}
