<?php

namespace Webbmaffian\ORM;

use Webbmaffian\ORM\Helpers\Driver;

class DB {
	static private $_instances = array();
	static private $_identifier_middleware = null;
	static private $_params_middleware = null;

	
	static public function instance($id = 'app') {
		$id = self::get_identifier($id);
		$type = isset($_ENV['DB_TYPE']) ? $_ENV['DB_TYPE'] : '';

		if(!isset(self::$_instances[$id])) {
			switch($type) {
				case '':
				case Driver::MYSQL:
				case 'mysql':
					self::$_instances[$id] = self::setup_mysql($id);
					break;
				
				case Driver::POSTGRES:
				case 'postgresql':
					self::$_instances[$id] = self::setup_postgresql($id);
					break;

				default:
					throw new Database_Exception('Driver not implemented.');
					break;
			}
		}

		return self::$_instances[$id];
	}


	static private function setup_mysql($id) {
		$defaults = array(
			'host' => isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : 'localhost',
			'port' => isset($_ENV['DB_PORT']) ? $_ENV['DB_PORT'] : 3306,
			'database' => isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : '',
			'user' => isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : 'root',
			'password' => ''
		);

		if(isset($_ENV['DB_PASSWORD'])) $defaults['password'] = $_ENV['DB_PASSWORD'];
		elseif(isset($_ENV['MYSQL_ROOT_PASSWORD'])) $defaults['password'] = $_ENV['MYSQL_ROOT_PASSWORD'];

		return new Mysql(self::get_params($id, $defaults));
	}


	static private function setup_postgresql($id) {
		$defaults = array(
			'host' => isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : 'localhost',
			'port' => isset($_ENV['DB_PORT']) ? $_ENV['DB_PORT'] : 5432,
			'dbname' => isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : '',
			'user' => isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : 'root',
			'password' => isset($_ENV['DB_PASSWORD']) ? $_ENV['DB_PASSWORD'] : '',
			'schema' => isset($_ENV['DB_SCHEMA']) ? $_ENV['DB_SCHEMA'] : 'public'
		);

		return new Postgres(self::get_params($id, $defaults));
	}


	static protected function get_identifier($id) {
		if(!is_null(self::$_identifier_middleware)) {
			$id = call_user_func(self::$_identifier_middleware, $id);
		}

		return $id;
	}


	static protected function get_params($id, $params) {
		if(!is_null(self::$_params_middleware)) {
			$params = call_user_func(self::$_params_middleware, $id, $params);
		}

		return $params;
	}


	static public function set_identifier_middleware($middleware) {
		if(!is_callable($middleware)) {
			throw new Database_Exception('Middleware is not callable.');
		}

		self::$_identifier_middleware = $middleware;
	}


	static public function set_params_middleware($middleware) {
		if(!is_callable($middleware)) {
			throw new Database_Exception('Middleware is not callable.');
		}

		self::$_params_middleware = $middleware;
	}
}