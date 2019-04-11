<?php
	namespace Webbmaffian\ORM;

	use Webbmaffian\ORM\Abstracts\Sql_Stmt;
	use Webbmaffian\ORM\Interfaces\Database_Stmt;
	use Webbmaffian\ORM\Helpers\Helper;
	use Webbmaffian\ORM\Helpers\Database_Exception;
	
	class Postgres_Stmt extends Sql_Stmt implements Database_Stmt {
		protected function create_stmt($query) {
			$this->stmt = pg_prepare($this->db->get_instance(), $this->name, $query);
		}

		
		public function execute() {
			$args = func_get_args();

			if(count($args) === 1) {
				$args = is_array($args[0]) ? $args[0] : array($args[0]);
			}

			if(!empty($args) && Helper::is_assoc($args)) {
				if(empty($this->mappings)) throw new Database_Exception('Missing parameter mappings.');

				$args = Sql::sort_params($args, $this->mappings);
			}

			if(!pg_send_execute($this->db->get_instance(), $this->name, $args)) {
				throw new Database_Exception('Failed to execute prepared statement.');
			}

			$result = pg_get_result($this->db->get_instance());
			
			return new Postgres_Result($result);
		}
	}