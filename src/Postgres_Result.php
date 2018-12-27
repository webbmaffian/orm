<?php
	namespace Webbmaffian\ORM;

	use Webbmaffian\ORM\Interfaces\Database_Result;
	use Webbmaffian\ORM\Helpers\Database_Exception;
	
	class Postgres_Result implements Database_Result {
		protected $resource;
		
		
		public function __construct($resource) {
			if(!is_resource($resource)) {
				throw new Database_Exception('Result must be a resource.');
			}
			
			$this->resource = $resource;
		}
		
		
		public function fetch_assoc() {
			return pg_fetch_assoc($this->resource);
		}
		
		
		public function fetch_row($row = null) {
			return pg_fetch_row($this->resource, $row);
		}
		
		
		public function fetch_value($row = 0, $field = 0) {
			try {
				return @pg_fetch_result($this->resource, $row, $field);
			} catch(\Exception $p) {
				return false;
			}
		}
		
		
		public function fetch_column($column = 0) {
			return pg_fetch_all_columns($this->resource, $column);
		}
		
		
		public function fetch_all() {
			return pg_fetch_all($this->resource);
		}
		
		
		public function num_rows() {
			return pg_num_rows($this->resource);
		}


		public function last_error() {
			return pg_result_error($this->resource);
		}
		
		
		public function free() {
			return pg_free_result($this->resource);
		}


		public function get_last_error() {
			return pg_result_error_field($this->resource, PGSQL_DIAG_MESSAGE_PRIMARY);
		}


		public function check_error() {
			$error = $this->get_last_error();

			if(!empty($error)) {
				throw new Database_Exception($error);
			}
		}
	}