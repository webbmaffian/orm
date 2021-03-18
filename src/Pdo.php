<?php
namespace Webbmaffian\ORM;

use Webbmaffian\ORM\Abstracts\Sql;
use Webbmaffian\ORM\Interfaces\Database;
use Webbmaffian\ORM\Helpers\Helper;
use Webbmaffian\ORM\Helpers\Database_Exception;

class Pdo extends Sql implements Database {
	const NULL_VALUE = 'NULL';
	const TRUE_VALUE = 1;
	const FALSE_VALUE = 0;

	/** @var \PDO $instance */
	protected $instance = null; 

	
	/**
	 *  $args should be an associative array with the following keys:
	 * - server
	 * - database
	 * - username
	 * - password
	 * - driver
	*/
	protected function setup_instance($args) {
		if(!extension_loaded('pdo')) {
			throw new Database_Exception('PDO extension is missing.');
		}

		if(empty($args['driver'])) {
			throw new Database_Exception('No driver specified.');
		}

		if(!in_array($args['driver'], \PDO::getAvailableDrivers())) {
			throw new Database_Exception('PDO driver "' . $args['driver'] . '" is missing.');
		}

		$driver = $args['driver'];
		$username = $args['username'] ?? null;
		$password = $args['password'] ?? null;

		unset($args['driver'], $args['username'], $args['password']);

		try {
			$this->instance = new \PDO($driver . ':' . Helper::get_query_string($args), $username, $password, [
				\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
			]);
		}
		catch(\PDOException $e) {
			throw new Database_Exception('Failed to create PDO instance.', 0, $e);
		}

		$this->schema = $args['database'];
	}


	public function test() {
		try {
			$this->instance->query('SELECT 1');
		}
		catch(\PDOException $e) {
			return false;
		}

		return true;
	}


	public function start_transaction() {
		if($this->is_transaction()) return;
		
		$this->is_transaction = true;
		return $this->instance->beginTransaction();
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
		$string = $this->instance->quote($string);

		return ($add_quotes ? $string : trim($string, '\'"'));
	}


	protected function run_query($query) {
		try {
			$resource = $this->instance->query($query);
		}
		catch(\PDOException $e) {
			throw new Database_Exception('Query error', 0, $e, $query);
		}

		return new Pdo_Result($resource);
	}
	

	public function query_params($query = '', $params = array()) {
		$stmt = $this->prepare($query);
		
		return $stmt->execute($params);
	}
	

	public function prepare($query) {
		if(!is_string($query)) {
			throw new Database_Exception('Query must be a string.');
		}

		return new Pdo_Stmt($this, $query);
	}

	
	public function get_last_id() {
		return (int)$this->instance->lastInsertId();
	}


	public function table_exists($table) {
		throw new Database_Exception('The "table_exists" method is not implemented');
	}

	
	public function insert($table, $params = array()) {
		throw new Database_Exception('The "insert" method is not implemented');
	}


	protected function get_insert_query($table, $params = array(), $quotes = true) {
		throw new Database_Exception('The "get_insert_query" method is not implemented');
	}

	
	public function update($table, $params = array(), $condition = array()) {
		throw new Database_Exception('The "update" method is not implemented');
	}


	protected function get_update_query($table, $params = array(), $condition = array(), $quotes = true) {
		throw new Database_Exception('The "get_update_query" method is not implemented');
	}
	

	// DEPRECATED
	public function insert_update($table, $params = array(), $unique_keys = array(), $auto_increment = null) {
		throw new Database_Exception('The "insert_update" method is not implemented');
	}


	protected function get_real_upsert_query($table, $param_keys = array(), $param_values = array(), $keys_to_update = array(), $auto_increment = null, $unique_keys = array()) {
		throw new Database_Exception('The "get_real_upsert_query" method is not implemented');
	}

	
	public function delete($table, $condition) {
		throw new Database_Exception('The "delete" method is not implemented');
	}


	protected function get_delete_query($table, $condition, $quotes = true) {
		throw new Database_Exception('The "get_delete_query" method is not implemented');
	}


	public function prepare_insert($table, $columns = array()) {
		throw new Database_Exception('The "prepare_insert" method is not implemented');
	}


	public function prepare_update($table, $columns = array(), $condition_columns = array()) {
		throw new Database_Exception('The "prepare_update" method is not implemented');
	}


	public function prepare_upsert($table, $columns = array(), $unique_keys = array(), $dont_update_keys = array(), $auto_increment = null) {
		throw new Database_Exception('The "prepare_upsert" method is not implemented');
	}


	public function prepare_delete($table, $condition_columns) {
		throw new Database_Exception('The "prepare_delete" method is not implemented');
	}

	
	public function last_error() {
		return $this->instance->errorInfo();
	}
	

	public function close() {

		// Yeah, this is the correct way in PDO.
		return $this->instance = null;
	}
	
	
	public function get_num_affected_rows() {
		throw new Database_Exception('The "get_num_affected_rows" method is not implemented');
	}


	static public function convert_query($query = '', &$params = array()) {
		throw new Database_Exception('The "convert_query" method is not implemented');
	}


	static public function sort_params($params = array(), $mappings = array()) {
		throw new Database_Exception('The "sort_params" method is not implemented');
	}
}