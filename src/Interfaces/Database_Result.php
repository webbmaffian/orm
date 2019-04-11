<?php

namespace Webbmaffian\ORM\Interfaces;

interface Database_Result {
	public function fetch_assoc();
	
	public function fetch_row($row = null);
	
	public function fetch_value($row = 0, $field = 0);
	
	public function fetch_column($column = 0);
	
	public function fetch_all();
	
	public function num_rows();
	
	public function free();
}