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
	*/
	protected function setup_instance($args) {
		if(!function_exists('mysqli_init')) {
			throw new Database_Exception('Mysqli driver is missing.');
		}
		
		$this->instance = new mysqli($args['host'], $args['user'], $args['password'], $args['database'], $args['port']);

		if(!$this->instance || $this->instance->connect_errno) {
			throw new Database_Exception("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
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
		$params = $this->convert_arrays($params);

		$query = 'INSERT INTO ' . $table . ' SET ' . $params;
		$result = $this->query($query);

		return true;
	}

	
	public function update($table, $params = array(), $condition = array()) {
		$params = $this->convert_arrays($params);
		$condition = $this->convert_arrays($condition, ' AND ');

		$query = 'UPDATE ' . $table . ' SET ' . $params . ' WHERE ' . $condition;
		$this->query($query);

		return true;
	}
	

	// Insert with the "on duplicate key update" approach.
	// This function can't be shared between Postgres and MySQL, as they work too different.
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

	
	public function delete($table, $condition) {
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
	
	
	static protected function get_param_placeholder($index) {
		return '?';
	}
}