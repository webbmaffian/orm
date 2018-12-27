<?php
	namespace Webbmaffian\ORM;

	use Webbmaffian\ORM\Interfaces\Database_Stmt;
	use Webbmaffian\ORM\Helpers\Helper;
	use Webbmaffian\ORM\Helpers\Database_Exception;
	
	class Postgres_Stmt implements Database_Stmt {
		static protected $next_name = 0;
		protected $instance;
		protected $name;
		protected $query;
		protected $mappings = array();
		
		
		public function __construct($instance, $query = '') {
			if(!is_resource($instance)) {
				throw new Database_Exception('Instance must be a resource.');
			}
			
			$this->instance = $instance;
			$this->name = 'stmt' . self::$next_name;
			$this->query = $query;
			
			if(preg_match(Postgres::VARIABLES_REGEX, $query) !== '') {
				list($query, $this->mappings) = Postgres::convert_query($query);
			}

			$stmt = pg_prepare($this->instance, $this->name, $query);
			
			if(!$stmt) {
				throw new Database_Exception(pg_last_error($this->instance));
			}
			
			self::$next_name++;
		}

		
		public function execute() {
			$args = func_get_args();

			if(count($args) === 1) {
				$args = is_array($args[0]) ? $args[0] : array($args[0]);
			}

			if(Helper::is_assoc($args)) {
				if(empty($this->mappings)) throw new Database_Exception('Missing parameter mappings.');

				$args = Postgres::sort_params($args, $this->mappings);
			}

			if(!pg_send_execute($this->instance, $this->name, $args)) {
				throw new Database_Exception('Failed to execute prepared statement.');
			}

			$result = pg_get_result($this->instance);
			
			return new Postgres_Result($result);
		}


		public function get_query() {
			return $this->query;
		}
	}