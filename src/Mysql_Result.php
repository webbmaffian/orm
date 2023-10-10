<?php

namespace Webbmaffian\ORM;

use Webbmaffian\ORM\Interfaces\Database_Result;
use Webbmaffian\ORM\Helpers\Database_Exception;
use \mysqli_result;

class Mysql_Result implements Database_Result {
	protected $resource;
	protected $insert_id;

	public function __construct($resource, $insert_id = null) {
		if(!is_bool($resource) && !($resource instanceof mysqli_result)) {
			throw new Database_Exception('Invalid type.');
		}

		$this->resource = $resource;
		$this->insert_id = $insert_id;
	}

	public function fetch_assoc() {
		if($this->resource instanceof mysqli_result) {
			return $this->resource->fetch_assoc();
		}

		return $this->resource;
	}
	
	public function fetch_row($row = null) {
		if($this->resource instanceof mysqli_result) {
			return $this->resource->fetch_row();
		}

		return $this->resource;
	}
	
	public function fetch_value($row = 0, $field = 0) {
		if($this->resource instanceof mysqli_result) {
			$result = $this->resource->fetch_row();

			if(isset($result[$field])) {
				return $result[$field];
			}

			return null;
		}

		return $this->resource;
	}
	
	public function fetch_column($column = 0) {
		if($this->resource instanceof mysqli_result) {
			return array_column($this->resource->fetch_all(is_int($column) ? MYSQLI_NUM : MYSQLI_ASSOC), $column);
		}

		return $this->resource;
	}
	
	public function fetch_all($type = MYSQLI_ASSOC) {
		if($this->resource instanceof mysqli_result) {
			return $this->resource->fetch_all($type);
		}

		return $this->resource;
	}
	
	public function num_rows() {
		if($this->resource instanceof mysqli_result) {
			return $this->resource->num_rows;
		}

		return $this->resource;
	}
	
	public function free() {
		if($this->resource instanceof mysqli_result) {
			$this->resource->free();
		}
	}

	public function get_insert_id() {
		return $this->insert_id;
	}
}