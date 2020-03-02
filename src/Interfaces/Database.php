<?php

namespace Webbmaffian\ORM\Interfaces;

interface Database {
	public function test();

	public function get_schema();

	public function start_transaction();

	public function end_transaction();

	public function is_transaction();

	public function rollback();

	public function escape_string($string);

	public function query();
	
	public function query_params($query = '', $params = array());
	
	public function prepare($query);

	public function get_result();
	
	public function get_value();
	
	public function get_column();
	
	public function get_row();
	
	public function get_last_id();

	public function table_exists($table);
	
	public function insert($table, $params = array());
	
	public function update($table, $params = array(), $condition = array());

	public function insert_update($table, $params = array(), $unique_keys = array(), $auto_increment = null);
	
	public function delete($table, $condition);
	
	public function last_error();
	
	public function close();
}