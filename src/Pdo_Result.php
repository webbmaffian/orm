<?php
	namespace Webbmaffian\ORM;

	use Webbmaffian\ORM\Interfaces\Database_Result;
	
	class Pdo_Result implements Database_Result {

		/** @var \PDOStatement */
		protected $resource;
		
		
		public function __construct(\PDOStatement $resource) {
			$this->resource = $resource;
		}
		
		
		public function fetch_assoc() {
			return $this->resource->fetch(\PDO::FETCH_ASSOC);
		}
		
		
		public function fetch_row($row = null) {
			return $this->resource->fetch(\PDO::FETCH_NUM);
		}
		
		
		public function fetch_value($row = 0, $field = 0) {
			try {
				$data = $this->resource->fetch(\PDO::FETCH_NUM, \PDO::FETCH_ORI_NEXT, $row);

				return $data[$field] ?? false;
			} catch(\PDOException $e) {
				return false;
			}
		}
		
		
		public function fetch_column($column = 0) {
			return $this->resource->fetchColumn($column);
		}
		
		
		public function fetch_all() {
			return $this->resource->fetchAll(\PDO::FETCH_ASSOC);
		}
		
		
		public function num_rows() {
			return $this->resource->rowCount();
		}


		public function last_error() {
			return $this->resource->errorInfo();
		}
		
		
		public function free() {
			return $this->resource->closeCursor();
		}


		public function get_last_error() {
			return $this->last_error();
		}
	}