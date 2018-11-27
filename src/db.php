<?php

namespace Webbmaffian\ORM;

use Helpers\Driver;

class DB {

	static private $_db = null;
	
	static public function instance($params = array(), $driver = '') {
		if(is_null(self::$_db)) {
			switch($driver) {
				case '':
				case Driver::MYSQL:
					self::setup_mysql($params);
					break;
				
				default:
					throw new Database_Exception('Driver not implemented.');
					break;
			}
		}

		return self::$_db;
	}


	static private function setup_mysql($params) {
		$defaults = array(
			'host' => isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : 'localhost',
			'port' => isset($_ENV['DB_PORT']) ? $_ENV['DB_PORT'] : 3306,
			'database' => isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : $dbname,
			'user' => isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : 'root',
			'password' => ''
		);

		if(isset($_ENV['DB_PASSWORD'])) $defaults['password'] = $_ENV['DB_PASSWORD'];
		elseif(isset($_ENV['MYSQL_ROOT_PASSWORD'])) $defaults['password'] = $_ENV['MYSQL_ROOT_PASSWORD'];

		$params = array_replace($params, $defaults);

		self::$_db = new Mysql($params);
	}
}