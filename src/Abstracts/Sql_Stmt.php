<?php
	namespace Webbmaffian\ORM\Abstracts;

	use Webbmaffian\ORM\Helpers\Helper;
	use Webbmaffian\ORM\Helpers\Database_Exception;
	
	abstract class Sql_Stmt {
		static protected $next_name = 0;
		protected $name;
		protected $db;
		protected $stmt;
		protected $query;
		protected $mappings = array();


		public function __construct($db, $query = '') {
			if(!$db instanceof Sql) {
				throw new Database_Exception('Invalid DB instance given.');
			}
			
			$this->db = $db;
			$this->name = 'stmt' . self::$next_name;
			$this->query = $query;
			$db_class = get_class($db);
			
			if(preg_match($db_class::VARIABLE_REGEX, $query) !== '') {
				list($query, $this->mappings) = $db_class::convert_query($query);
			}

			$this->create_stmt($query);
			
			if(!$this->stmt) {
				throw new Database_Exception($this->db->last_error());
			}
			
			self::$next_name++;
		}


		abstract protected function create_stmt($query);


		public function get_query() {
			return $this->query;
		}
	}
