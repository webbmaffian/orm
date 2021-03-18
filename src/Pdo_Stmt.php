<?php
	namespace Webbmaffian\ORM;

	use Webbmaffian\ORM\Abstracts\Sql_Stmt;
	use Webbmaffian\ORM\Interfaces\Database_Stmt;
	use Webbmaffian\ORM\Helpers\Helper;
	use Webbmaffian\ORM\Helpers\Database_Exception;
	
	class Pdo_Stmt extends Sql_Stmt implements Database_Stmt {
		protected function create_stmt($query) {
			$this->stmt = $this->db->get_instance()->prepare($query);
		}

		
		public function execute() {
			$args = func_get_args();

			if(count($args) === 1) {
				$args = is_array($args[0]) ? $args[0] : array($args[0]);
			}

			if(!empty($args) && Helper::is_assoc($args)) {
				$new_args = [];

				foreach($args as $key => $value) {
					$new_args[':' . $key] = $value;
				}

				$args = $new_args;
			}

			try {
				$this->stmt->execute($args);
			}
			catch(\PDOException $e) {
				throw new Database_Exception('Failed to execute prepared statement.', 0, $e, $this->get_query(), $args);
			}
			
			return new Pdo_Result($this->stmt);
		}
	}