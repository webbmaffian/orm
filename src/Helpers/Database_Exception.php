<?php

namespace Webbmaffian\ORM\Helpers;

use Throwable;

class Database_Exception extends \Exception {
	protected $_query;
	protected $_args;

	
	public function __construct($message = '', $code = 0 , ?Throwable $previous, $query = null, $args = null) {
		$this->_query = $query;
		$this->_args = $args;
		
		parent::__construct($message, $code, $previous);
	}


	public function getQuery() {
		return $this->_query;
	}


	public function getArgs() {
		return $this->_args;
	}
}