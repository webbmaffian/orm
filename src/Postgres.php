<?php
	namespace Webbmaffian\ORM;

	use Webbmaffian\ORM\Abstracts\Sql;
	use Webbmaffian\ORM\Interfaces\Database;
	use Webbmaffian\ORM\Helpers\Helper;
	use Webbmaffian\ORM\Helpers\Database_Exception;
	
	class Postgres extends Sql implements Database {
		const NULL_VALUE = NULL;
		const TRUE_VALUE = 'true';
		const FALSE_VALUE = 'false';

		
		/* $args should be an associative array with the following keys:
			- host
			- port
			- dbname
			- user
			- password
			- schema
		*/
		protected function setup_instance($args) {
			if(!function_exists('pg_connect')) {
				throw new Database_Exception('Postgres driver is missing.');
			}

			if(!isset($args['schema'])) {
				throw new Database_Exception('Schema is missing from argument list.');
			}

			unset($args['schema']);
			
			$this->instance = @pg_connect(http_build_query($args, null, ' '));
			
			if(!$this->instance) {
				throw new Database_Exception('Failed to connect to PostgreSQL.');
			}

			$this->set_schema($args['schema']);
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


		public function escape_string($string, $add_quotes = false) {
			return ($add_quotes ? pg_escape_literal($this->instance, $string) : pg_escape_string($this->instance, $string));
		}


		public function is_api() {
			return false;
		}


		public function set_schema($schema = '') {
			$this->schema = ($schema ?: $this->schema);
			pg_query($this->instance, 'SET search_path TO ' . $this->schema);
		}


		protected function run_query($query) {
			if(!pg_send_query($this->instance, $query)) {
				throw new Database_Exception('Failed to execute query.');
			}

			$result = new Postgres_Result(pg_get_result($this->instance));
			$result->check_error();
			
			return $result;
		}
		
		
		protected function query_params($query = '', $params = array()) {
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
			
			return new Postgres_Stmt($this, $query);
		}


		public function get_last_id() {
			return (int)$this->get_value('SELECT lastval();');
		}


		public function table_exists($table) {
			$class = $this->get_value('SELECT to_regclass(?);', $table);
			return !is_null($class);
		}


		public function insert($table, $params = array()) {
			$params = $this->format_values($params, false);
			
			$query = pg_insert($this->instance, $table, $params, PGSQL_DML_STRING | PGSQL_DML_ESCAPE);
			$this->query($query);
			
			return true;
		}


		public function update($table, $params = array(), $condition = array()) {
			$params = $this->format_values($params, false);
			
			$query = pg_update($this->instance, $table, $params, $condition, PGSQL_DML_STRING | PGSQL_DML_ESCAPE);
			$this->query($query);
			
			return true;
		}


		// Insert with the "on duplicate key update" approach.
		// This function can't be shared between Postgres and MySQL, as they work too different.
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

		
		static protected function get_param_placeholder($index) {
			return '$' . $index;
		}


		// Adds support for question marks as parameter placeholders - MySQL-style.
		static public function convert_numeric($query) {
			$i = 0;
			
			$new_query = preg_replace_callback('/\?/', function($matches) use (&$i) {
				return '$' . ++$i;
			}, $query);

			return $new_query;
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
	}