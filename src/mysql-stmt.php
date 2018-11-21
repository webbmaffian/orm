<?php

namespace Webbmaffian\ORM;

use Interfaces\Database_Stmt;
use Helpers\Helper;
use Helpers\Database_Exception;
use \mysqli;

class Mysql_Stmt implements Database_Stmt {
	protected $instance;
	protected $stmt;
	protected $query;
	protected $mappings = array();
	
	
	public function __construct($instance, $query = '') {
		if(!($instance instanceof mysqli)) {
			throw new Database_Exception('Instance must be of type mysqli.');
		}
		
		$this->instance = $instance;
		$this->query = $query;
		
		if(Helper::match('/=\s*:/', $query) !== '') list($query, $this->mappings) = Mysql::convert_query($query);

		$this->stmt = $this->instance->prepare($query);
		
		if(!$this->stmt) {
			throw new Database_Exception($this->instance->error);
		}
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


	public function get_query() {
		return $this->query;
	}
}