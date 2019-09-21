<?php
namespace Webbmaffian\ORM;

use Webbmaffian\ORM\Abstracts\Sql;
use Webbmaffian\ORM\Interfaces\Database;
use Webbmaffian\ORM\Helpers\Helper;
use Webbmaffian\ORM\Helpers\Database_Exception;
use \mysqli;

class Mysql extends Sql implements Database {
	const NULL_VALUE = 'NULL';
	const TRUE_VALUE = 1;
	const FALSE_VALUE = 0;

	
	/**
	 *  $args should be an associative array with the following keys:
	 * - host
	 * - port
	 * - dbname
	 * - user
	 * - password
	 * - ca_certificate (if SSL)
	*/
	protected function setup_instance($args) {
		if(!function_exists('mysqli_init')) {
			throw new Database_Exception('Mysqli driver is missing.');
		}
		
		$this->instance = new mysqli();

		if(!$this->instance) {
			throw new Database_Exception('Failed to create mysqli instance.');
		}

		if($args['ca_certificate']) {
			if(!is_readable($args['ca_certificate'])) {
				throw new Database_Exception('CA certificate does not exist or is not readable.');
			}

			$this->instance->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
			$this->instance->ssl_set(null, null, $args['ca_certificate'], null, null);
		}

		if($this->instance->real_connect($args['host'], $args['user'], $args['password'], $args['database'], $args['port'])) {
			throw new Database_Exception('Failed to connect to MySQL: (' . $this->instance->connect_errno . ') ' . $this->instance->connect_error);
		}

		$this->instance->set_charset('utf8');
		$this->schema = $args['database'];
	}


	public function test() {
		return $this->instance->ping();
	}


	public function start_transaction() {
		if($this->is_transaction()) return;
		
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


	public function escape_string($string, $add_quotes = false) {
		$string = $this->instance->escape_string($string);

		return ($add_quotes ? ('"' . $string . '"') : $string);
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


	protected function run_query($query) {
		// Can be boolean or mysqli_result object. We always want to return our own Mysql_Result.
		$resource = $this->instance->query($query);

		if(!$resource) {
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

		return new Mysql_Stmt($this, $query);
	}

	
	public function get_last_id() {
		return (int)$this->instance->insert_id;
	}


	public function table_exists($table) {
		$res = $this->query('SHOW TABLES LIKE ' . $table);
		return $res->num_rows() > 0;
	}

	
	public function insert($table, $params = array()) {
		$this->query($this->get_insert_query($table, $params));

		return true;
	}


	protected function get_insert_query($table, $params = array(), $quotes = true) {
		$params = $this->convert_arrays($params, ', ', $quotes);

		return 'INSERT INTO ' . $table . ' SET ' . $params;
	}

	
	public function update($table, $params = array(), $condition = array()) {
		$this->query($this->get_update_query($table, $params, $condition));

		return true;
	}


	protected function get_update_query($table, $params = array(), $condition = array(), $quotes = true) {
		$params = $this->convert_arrays($params, ', ', $quotes);
		$condition = $this->convert_arrays($condition, ' AND ', $quotes);

		return 'UPDATE ' . $table . ' SET ' . $params . ' WHERE ' . $condition;
	}
	

	// DEPRECATED
	public function insert_update($table, $params = array(), $unique_keys = array(), $auto_increment = null) {
		$this->upsert($table, $params, $unique_keys, array(), $auto_increment);

		return true;
	}


	protected function get_real_upsert_query($table, $param_keys = array(), $param_values = array(), $keys_to_update = array(), $auto_increment = null, $unique_keys = array()) {

		// Turn array('key' => 'value') to array('key' => 'VALUES(key)')
		$keys_to_update = array_map(function($key) {
			return 'VALUES(' . $key . ')';
		}, array_combine($keys_to_update, $keys_to_update));

		if($auto_increment) {
			$keys_to_update[$auto_increment] = 'LAST_INSERT_ID(' . $auto_increment . ')';
		}
		
		return 'INSERT INTO ' . $table . '(' . implode(', ', $param_keys) . ') VALUES(' . implode(', ', $param_values) . ') ON DUPLICATE KEY UPDATE ' . $this->get_param_string($keys_to_update);
	}

	
	public function delete($table, $condition) {
		$this->query($this->get_delete_query($table, $condition));

		return true;
	}


	protected function get_delete_query($table, $condition, $quotes = true) {
		$condition = $this->convert_arrays($condition, ' AND ', $quotes);

		return 'DELETE FROM ' . $table . ' WHERE ' . $condition;
	}


	public function prepare_insert($table, $columns = array()) {
		$params = $this->columns_to_prepared_params($columns);

		return $this->prepare($this->get_insert_query($table, $params, false));
	}


	public function prepare_update($table, $columns = array(), $condition_columns = array()) {
		$params = $this->columns_to_prepared_params($columns);
		$condition = $this->columns_to_prepared_params($condition_columns);

		return $this->prepare($this->get_update_query($table, $params, $condition, false));
	}


	public function prepare_upsert($table, $columns = array(), $unique_keys = array(), $dont_update_keys = array(), $auto_increment = null) {
		if(is_string($dont_update_keys) && is_null($auto_increment)) {
			$dont_update_keys = array();
			$auto_increment = $dont_update_keys;
		}

		$params = $this->columns_to_prepared_params($columns);

		return $this->prepare($this->get_upsert_query($table, $params, $unique_keys, $dont_update_keys, $auto_increment, false));
	}


	public function prepare_delete($table, $condition_columns) {
		$condition = $this->columns_to_prepared_params($condition_columns);

		return $this->prepare($this->get_delete_query($table, $condition));
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


	private function convert_arrays($arr = array(), $delimiter = ', ', $quotes = true) {
		if(!is_array($arr)) {
			$arr = array($arr);
		}

		if(!Helper::is_assoc($arr)) {
			return implode($delimiter, $arr);
		}

		return $this->get_param_string($this->format_values($arr, $quotes), $delimiter);
	}


	private function columns_to_prepared_params($columns = array()) {
		$params = array();

		foreach($columns as $column) {
			$params[$column] = ':' . $column;
		}

		return $params;
	}


	static public function convert_query($query = '') {
		$mappings = array();

		$new_query = preg_replace_callback(self::VARIABLE_REGEX, function($matches) use (&$mappings) {
			$mappings[] = $matches[1];
			
			return '?';
		}, $query);

		return array($new_query, $mappings);
	}


	static public function sort_params($params = array(), $mappings = array()) {
		$arr = array();

		foreach($mappings as $value) {
			$arr[] = $params[$value];
		}

		return $arr;
	}
}