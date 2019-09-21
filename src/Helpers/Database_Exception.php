<?php
	namespace Webbmaffian\ORM\Helpers;

	class Database_Exception extends \Exception {
		protected $_query;
		protected $_args;

		
		public function __construct($message = '', $code = 0 , \Exception $previous = null, $query = null, $args = null) {
			$this->_field = $field;
			parent::__construct($message, $code, $previous);
		}


		public function getQuery() {
			return $this->_query;
		}


		public function getArgs() {
			return $this->_args;
		}
	}