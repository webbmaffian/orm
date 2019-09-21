<?php

namespace Webbmaffian\ORM;
use Webbmaffian\ORM\Abstracts\Sql_Stmt;
use Webbmaffian\ORM\Interfaces\Database_Stmt;
use Webbmaffian\ORM\Helpers\Helper;
use Webbmaffian\ORM\Helpers\Database_Exception;

class Mysql_Stmt extends Sql_Stmt implements Database_Stmt {
	protected function create_stmt($query) {
		$this->stmt = $this->db->get_instance()->prepare($query);
	}

	
	public function execute() {
		$args = func_get_args();

		if(count($args) === 1) {
			$args = is_array($args[0]) ? $args[0] : array($args[0]);
		}

		if(!empty($args)) {
			if(Helper::is_assoc($args)) {
				if(empty($this->mappings)) throw new Database_Exception('Missing parameter mappings.');

				$args = Mysql::sort_params($args, $this->mappings);
			}

			$types = '';

			foreach($args as $p) {
				if(is_string($p)) {
					$types .= 's';
				} elseif(is_integer($p)) {
					$types .= 'i';
				} elseif(is_float($p)) {
					$types .= 'd';
				} else {
					$types .= 'b';
				}
			}

			$this->stmt->bind_param($types, ...$args);
		}

		$result = $this->stmt->execute();
		
		if(!$result) {
			throw new Database_Exception($this->stmt->error);
		}
		
		return new Mysql_Result($this->stmt->get_result());
	}
}