<?php

namespace Webbmaffian\ORM;

use Webbmaffian\ORM\Interfaces\Database;
use Webbmaffian\ORM\Helpers\Helper;
use Webbmaffian\ORM\Helpers\Database_Exception;
use \mysqli;

class Mysql implements Database {

	protected $instance = null;
	protected $is_transaction = false;
	protected $savepoint_increment = 0;

	/**
	 *  $args should be an associative array with the following keys:
	 * - host
	 * - port
	 * - dbname
	 * - user
	 * - password
	*/
	public function __construct($args) {
		if(empty($args)) {
			throw new Database_Exception('Missing arguments.');
		}

		if(!is_array($args)) {
			throw new Database_Exception('Arguments must be an array.');
		}

		if(!function_exists('mysqli_init')) {
			throw new Database_Exception('Mysqli driver is missing.');
		}
		
		$this->instance = new mysqli($args['host'], $args['user'], $args['password'], $args['database'], $args['port']);

		if(!$this->instance || $this->instance->connect_errno) {
			throw new Database_Exception("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
		}

		$this->instance->set_charset('utf8');
	}

	public function test() {
		return $this->instance->ping();
	}


	public function start_transaction() {
		if($this->is_transaction()) {
			throw new Database_Exception('Transaction already started.');
		}
		
		$this->is_transaction = true;
		return $this->instance->begin_transaction();
	}


	public function end_transaction() {
		if(!$this->is_transaction()) return;
		
		$this->is_transaction = false;
		return $this->instance->commit();
	}


	public function rollback() {
		if(!$this->is_transaction()) return;
		
		$this->is_transaction = false;
		return $this->instance->rollback();
	}


	public function escape_string($string) {
		return $this->instance->escape_string($string);
	}
	
	
	public function is_transaction() {
		return $this->is_transaction;
	}
	
	
	public function add_savepoint($name = null) {
		if(is_null($name)) {
			$name = 'sp' . ++$this->savepoint_increment;
		}
		
		$name = Sanitize::key($name);
		
		$this->instance->savepoint($name);
		
		return $name;
	}
	
	
	public function rollback_savepoint($name = null) {
		if(is_null($name)) {
			$name = 'sp' . $this->savepoint_increment;
		}
		
		$name = Sanitize::key($name);
		
		return $this->instance->query('ROLLBACK TO ' . $name);
	}
	
	
	public function release_savepoint($name = null) {
		if(is_null($name)) {
			$name = 'sp' . $this->savepoint_increment;
		}
		
		$name = Sanitize::key($name);
		
		$this->instance->release_savepoint($name);
	}


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

		// Can be boolean or mysqli_result object. We always want to return our own Mysql_Result.
		$resource = $this->instance->query($query);

		if(!$resource) {
			Log::query_errors($this->last_error(), 'for query:' . "\n" . $query . "\n");
			throw new Database_Exception($this->last_error());
		}

		return new Mysql_Result($resource);
	}
	

	public function query_params($query = '', $params = array()) {
		if(Helper::is_assoc($params)) {
			list($query, $params) = self::convert_assoc($query, $params);
		}

		$stmt = $this->prepare($query);
		
		return $stmt->execute($params);
	}
	

	public function prepare($query) {
		if(!is_string($query)) {
			throw new Database_Exception('Query must be a string.');
		}

		return new Mysql_Stmt($this->instance, $query);
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

	
	public function get_last_id() {
		return $this->instance->insert_id;
	}


	public function table_exists($table) {
		$res = $this->query('SHOW TABLES LIKE ' . $table);
		return $res->num_rows() > 0;
	}

	
	public function insert($table, $params = array(), $options = null) {
		$params = $this->convert_arrays($params);

		$query = 'INSERT INTO ' . $table . ' SET ' . $params;
		$result = $this->query($query);

		return true;
	}

	
	public function update($table, $params = array(), $condition = array(), $options = null) {
		$params = $this->convert_arrays($params);
		$condition = $this->convert_arrays($condition, ' AND ');

		$query = 'UPDATE ' . $table . ' SET ' . $params . ' WHERE ' . $condition;
		$result = $this->query($query);

		return true;
	}
	
	// Insert with the "on duplicate key update" approach
	public function insert_update($table, $params = array(), $unique_keys = array(), $auto_increment = null) {
		$params = $this->format_values($params);
		
		if(!is_array($unique_keys)) {
			$unique_keys = array($unique_keys);
		}
		
		$param_keys = array_keys($params);
		$param_values = array_values($params);
		
		$param_keys_non_unique = array_diff($param_keys, $unique_keys);
		$param_keys_non_unique = array_map(function($key) {
			return 'VALUES(' . $key . ')';
		}, array_combine($param_keys_non_unique, $param_keys_non_unique));
		
		$query = 'INSERT INTO ' . $table . '(' . implode(', ', $param_keys) . ') VALUES(' . implode(', ', $param_values) . ') ON DUPLICATE KEY UPDATE ' . $this->get_param_string($param_keys_non_unique);
		
		if($auto_increment) {
			$query .= sprintf(', %s = LAST_INSERT_ID(%s)', $auto_increment, $auto_increment);
		}
		
		$result = $this->query($query);

		return true;
	}

	
	public function delete($table, $condition, $options = null) {
		$condition = $this->convert_arrays($condition, ' AND ');

		$query = 'DELETE FROM ' . $table . ' WHERE ' . $condition;
		$result = $this->query($query);

		return true;
	}

	
	public function last_error() {
		return $this->instance->error;
	}
	

	public function close() {
		return $this->instance->close();
	}
	
	
	public function get_num_affected_rows() {
		return $this->instance->affected_rows;
	}


	private function convert_arrays($arr = array(), $delimiter = ', ') {
		return $this->get_param_string($this->format_values($arr), $delimiter);
	}
	
	
	private function format_values($params = array()) {
		foreach($params as $key => $value) {
			if(is_array($value)) {
				$value = json_encode($value);
			}
			elseif($value instanceof \DateTime) {
				$value = $value->format('Y-m-d H:i:s');
			}
			
			if(is_string($value)) {
				$params[$key] = '"' . $this->escape_string(trim($value)) . '"';
			}
			elseif(is_null($value)) {
				$params[$key] = 'NULL';
			}
		}
		
		return $params;
	}
	
	
	private function get_param_string($params = array(), $delimiter = ', ') {
		return urldecode(http_build_query($params, '', $delimiter));
	}


	/**
	 * Converts associative parametered queries like:
	 * $query = SELECT * FROM shops WHERE name = :name AND something = :somewhat OR another_name = :name
	 * $params = array('name' => 'A Name', 'somewhat' => 'Some data')
	 * 
	 * ... to:
	 * $query = SELECT * FROM shops WHERE name = ? AND something = ? OR another_name = ?
	 * $params = array('A Name', 'Some data', 'A name')
	 * 
	 * ... in order to run mysqli prepared statements
	 */
	static private function convert_assoc($query = '', $params) {
		list($new_query, $mappings) = self::convert_query($query);

		$params = self::sort_params($params, $mappings);

		return array($new_query, $params);
	}

	static public function convert_query($query = '') {
		$mappings = array();

		$new_query = preg_replace_callback('/\:([a-zA-Z0-9_]+)/', function($matches) use (&$mappings) {
			$mappings[] = $matches[1];

			return '?';
		}, $query);

		return array($new_query, $mappings);
	}


	static public function sort_params($params, $mappings) {
		$arr = array();

		foreach($mappings as $value) {
			$arr[] = $params[$value];
		}

		return $arr;
	}
}