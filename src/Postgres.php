<?php
	namespace Webbmaffian\ORM;

	use Webbmaffian\ORM\Interfaces\Database;
	use Webbmaffian\ORM\Helpers\Helper;
	use Webbmaffian\ORM\Helpers\Database_Exception;
	
	class Postgres implements Database {
		const VARIABLES_REGEX = '/([^\:])\:([a-z0-9_]+)/i';
		const NUM_PARAMS_REGEX = '/\s*\?/';

		protected $instance = null;
		protected $schema = null;
		
		/* $args should be an associative array with the following keys:
			- host
			- port
			- dbname
			- user
			- password
			- Schema
		*/
		public function __construct($args = array()) {
			if(empty($args)) {
				throw new Database_Exception('Missing arguments.');
			}

			if(!is_array($args)) {
				throw new Database_Exception('Arguments must be an array.');
			}

			if(!function_exists('pg_connect')) {
				throw new Database_Exception('Postgres driver is missing.');
			}

			if(!isset($args['schema'])) {
				throw new Database_Exception('Schema is missing from argument list.');
			}

			$this->schema = $args['schema'];
			unset($args['schema']);
			
			$this->instance = @pg_connect(http_build_query($args, null, ' '));
			
			if(!$this->instance) {
				throw new Database_Exception('Failed to connect to PostgreSQL.');
			}

			$this->set_schema();
		}


		public function is_api() {
			return false;
		}


		public function set_schema($schema = '') {
			$this->schema = ($schema ?: $this->schema);
			pg_query($this->instance, 'SET search_path TO ' . $this->schema);
		}


		public function get_schema() {
			return $this->schema;
		}


		public function test() {
			return pg_connection_status($this->instance) === PGSQL_CONNECTION_OK;
		}


		public function start_transaction() {
			$this->query('BEGIN');
		}
	
	
		public function end_transaction() {
			$this->query('COMMIT');
		}
	
	
		public function rollback() {
			$this->query('ROLLBACK');
		}
		
		
		public function query() {
			$args = func_get_args();
			$query = array_shift($args);

			if(!is_string($query)) {
				throw new Database_Exception('Query must be a string.');
			}

			$query = self::format_query($query);

			if(!empty($args)) {
				if(count($args) === 1) {
					$args = is_array($args[0]) ? $args[0] : array($args[0]);
				}

				return $this->query_params($query, $args);
			}
			
			if(!pg_send_query($this->instance, $query)) {
				throw new Database_Exception('Failed to execute query.');
			}

			$result = new Postgres_Result(pg_get_result($this->instance));

			$result->check_error();
			
			return $result;
		}
		
		
		public function query_params($query = '', $params = array()) {
			if(Helper::is_assoc($params)) {
				list($query, $params) = self::convert_assoc($query, $params);
			}
			else {
				$query = self::convert_numeric($query);
			}

			if(!pg_send_query_params($this->instance, $query, $params)) {
				throw new Database_Exception('Failed to execute query.');
			}

			$result = new Postgres_Result(pg_get_result($this->instance));

			$result->check_error();
			
			return $result;
		}
		
		
		public function prepare($query) {
			if(!is_string($query)) {
				throw new Database_Exception('Query must be a string.');
			}

			$query = self::format_query($query);
			
			return new Postgres_Stmt($this->instance, $query);
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
			return $this->get_value('SELECT lastval();');
		}


		public function table_exists($table) {
			$class = $this->get_value("SELECT to_regclass('$table');");
			return !is_null($class);
		}


		public function schema_exists(string $schema) {
			$result = $this->get_value('SELECT EXISTS(SELECT 1 FROM pg_namespace WHERE nspname = :schema)::int', array('schema' => $schema));
			return (bool)$result;
		}


		public function create_schema(string $schema) {
			if(empty($schema)) {
				throw new Database_Exception('Schema can not be empty.');
			}

			$this->get_value('CREATE SCHEMA IF NOT EXISTS ' . $schema);
			$this->set_schema($schema);

			try {
				$this->update_schema();
			} catch(Database_Exception $e) {
				throw new Database_Exception('Failed to setup database tables.', null, $e);
			}
		}


		public function update_schema() {
			try {
				$this->start_transaction();

				$dbv = new DBV($this, $this->schema);
				$changes = $dbv->compare(Common::ROOT . '/config/dumpfile-tenant.json');
				$null_values = array();

				if(!empty($changes)) {
					foreach($changes as $change) {
						$result = $change->execute();

						$error = $result->get_last_error();
						if(!empty($error) && strpos($error, 'null values') !== false) {
							$error_parts = explode(' ', $error);
							$null_values[] = trim($error_parts[1], '"');
						}
					}
				}

				$this->end_transaction();
			} catch(Database_Exception $e) {
				$this->rollback();
				throw new Database_Exception('Unable to update database.', null, $e);
			}

			return $null_values;
		}


		public function insert($table, $params = array()) {
			$params = $this->convert_arrays($params);
			$params = $this->format_values($params, false);
			
			$query = pg_insert($this->instance, $table, $params, PGSQL_DML_STRING | PGSQL_DML_ESCAPE);
			$this->query($query);
			
			return true;
		}


		// Insert with the "on duplicate key update" approach
		public function insert_update($table, $params = array(), $unique_keys = array(), $dont_update_keys = array(), $return_key = null) {
			$params = $this->format_values($params);

			if(!is_array($unique_keys)) {
				$unique_keys = array($unique_keys);
			}
			
			$non_unique_params = array_filter($params, function($v, $k) use ($unique_keys) {
				return !in_array($k, $unique_keys);
			}, ARRAY_FILTER_USE_BOTH);

			if(!empty($dont_update_keys)) {
				$non_unique_params = array_diff_key($non_unique_params, array_flip($dont_update_keys));
			}

			$query = 'INSERT INTO ' . $table . ' (' . implode(', ', array_keys($params)) . ') VALUES (' . implode(', ', $params) . ') ON CONFLICT (' . implode(', ', $unique_keys) . ') DO UPDATE SET ' . $this->get_param_string($non_unique_params);
			
			if(!is_null($return_key)) {
				$query .= ' RETURNING ' . $return_key;
			}

			return $this->query($query);
		}
		
		
		public function update($table, $params = array(), $condition = array()) {
			$params = $this->convert_arrays($params);
			$params = $this->format_values($params, false);
			
			$query = pg_update($this->instance, $table, $params, $condition, PGSQL_DML_STRING | PGSQL_DML_ESCAPE);
			$this->query($query);
			
			return true;
		}
		
		
		public function delete($table, $condition) {
			$query = pg_delete($this->instance, $table, $condition, PGSQL_DML_STRING | PGSQL_DML_ESCAPE);
			$this->query($query);
			
			return true;
		}
		
		
		public function last_error() {
			return pg_last_error($this->instance);
		}
		
		
		public function close() {
			return pg_close($this->instance);
		}


		private function convert_arrays($arr = array()) {
			foreach($arr as $key => $value) {
				if(is_array($value)) {
					array_walk_recursive($value, function(&$val, $key) {
						$val = addslashes($val);
					});

					$arr[$key] = json_encode($value);
				}
			}

			return $arr;
		}


		// Postgres doesn't support double-quote strings
		private function format_query($query) {
			return str_replace('"', '\'', $query);
		}


		private function format_values($params = array(), $quotes = true) {
			foreach($params as $key => $value) {
				if(is_array($value)) {
					$value = json_encode($value);
				}
				elseif($value instanceof \DateTime) {
					$value = $value->format('Y-m-d H:i:s');
				}
				
				if(is_numeric($value)) {
					$params[$key] = (int)$value;
				}
				elseif(is_string($value)) {
					$value = trim($value);
					if($quotes) {
						$params[$key] = "'" . pg_escape_string($this->instance, $value) . "'";
					} else {
						$params[$key] = pg_escape_string($this->instance, $value);
					}
				}
				elseif(is_bool($value)) {
					$params[$key] = $value ? 'true' : 'false';
				}
				elseif(is_null($value)) {
					$params[$key] = NULL;
				}
			}
			
			return $params;
		}
		

		private function get_param_string($params = array(), $delimiter = ', ') {
			return urldecode(http_build_query($params, '', $delimiter));
		}


		public function escape_string($string, $add_quotes = false) {
			return ($add_quotes ? pg_escape_literal($this->instance, $string) : pg_escape_string($this->instance, $string));
		}


		/* 	Converts associative parametered queries like:
				$query = SELECT * FROM shops WHERE name = :name AND something = :somewhat OR another_name = :name
				$params = array('name' => 'A Name', 'somewhat' => 'Some data')
		
			... to:
				$query = SELECT * FROM shops WHERE name = $1 AND something = $2 OR another_name = $1
				$params = array('A Name', 'Some data')
		
			... in order to run pg_query_params
		*/
		static public function convert_assoc($query = '', $params) {
			list($new_query, $mappings) = self::convert_query($query);
			
			$params = self::sort_params($params, $mappings);

			return array($new_query, $params);
		}


		static public function convert_query($query = '') {
			$mappings = array();

			$new_query = preg_replace_callback(self::VARIABLES_REGEX, function($matches) use (&$mappings) {
				$name = $matches[2];
				
				if(!isset($mappings[$name])) {
					$mappings[$name] = (empty($mappings) ? 1 : end($mappings) + 1);
				}
				
				return $matches[1] . '$' . $mappings[$name];
			}, $query);

			return array($new_query, $mappings);
		}
		
		
		static public function sort_params($params = array(), $mapping = array()) {
			$arr = array();
			foreach($mapping as $key => $value) {
				$arr[] = $params[$key];
			}

			return $arr;
		}


		static public function convert_numeric($query) {
			$i = 0;
			
			$new_query = preg_replace_callback(self::NUM_PARAMS_REGEX, function($matches) use (&$i) {
				return ' $' . ++$i;
			}, $query);

			return $new_query;
		}
	}