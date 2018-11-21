<?php

namespace Webbmaffian\ORM\Interfaces;

interface Database {
	public function test();

	public function start_transaction();

	public function end_transaction();

	public function rollback();

	public function query();
	
	public function query_params($query = '', $params = array());
	
	public function prepare($query);
	
	public function get_value();
	
	public function get_column();
	
	public function get_row();
	
	public function get_last_id();

	public function table_exists($table);
	
	public function insert($table, $params = array(), $options = null);
	
	public function update($table, $params = array(), $condition = array(), $options = null);
	
	public function delete($table, $condition, $options = null);
	
	public function last_error();
	
	public function close();
}