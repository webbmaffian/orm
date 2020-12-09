<?php

namespace Webbmaffian\ORM;

use Webbmaffian\ORM\Helpers\Driver;

class DB {
	static private $_instances = array();
	static private $_identifier_middlewares = null;
	static private $_params_middlewares = null;

	
	static public function instance($id = 'app') {
		$id = self::get_identifier($id);
		$type = isset($_ENV['DB_TYPE']) ? $_ENV['DB_TYPE'] : Driver::MYSQL;

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

		if(isset($_ENV['DB_CA_CERTIFICATE'])) {
			$defaults['ca_certificate'] = $_ENV['DB_CA_CERTIFICATE'];
		}

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
		if(!is_null(self::$_identifier_middlewares)) {
			foreach(self::$_identifier_middlewares as $callback) {
				if($_id = call_user_func($callback, $id)) {
					$id = $_id;
				}
			}
		}

		return $id;
	}


	static protected function get_params($id, $params) {
		if(!is_null(self::$_params_middlewares)) {
			foreach(self::$_params_middlewares as $callback) {
				if($_params = call_user_func($callback, $id, $params)) {
					$params = $_params;
				}
			}
		}

		return $params;
	}


	static public function set_identifier_middleware($middleware) {
		self::add_identifier_middleware($middleware);
	}


	static public function add_identifier_middleware($middleware) {
		if(!is_callable($middleware)) {
			throw new Database_Exception('Middleware is not callable.');
		}

		if(is_null(self::$_identifier_middlewares)) {
			self::$_identifier_middlewares = [];
		}

		self::$_identifier_middlewares[] = $middleware;
	}


	static public function set_params_middleware($middleware) {
		self::add_params_middleware($middleware);
	}


	static public function add_params_middleware($middleware) {
		if(!is_callable($middleware)) {
			throw new Database_Exception('Middleware is not callable.');
		}

		if(is_null(self::$_params_middlewares)) {
			self::$_params_middlewares = [];
		}

		self::$_params_middlewares[] = $middleware;
	}
}
