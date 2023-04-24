<?php

namespace Webbmaffian\ORM\Helpers;

class Helper {
	static public function is_assoc($arr) {
		if(empty($arr)) return false;
		
		if(!is_array($arr)) throw new Database_Exception('Input must be of type Array');

		$keys = array_keys($arr);
		if(is_string($keys[0])) return true;

		return false;
	}


	/* 
	 * As PHP's is_numeric() thinks that HEX strings are numeric, we need a function
	 * that checks it it's numeric for real.
	 * 
	 * Accepts: 1, 1.1, "1", "1.1"
	 * 
	 * Does not accept: "1.1.1"
	 */
	static public function is_numeric($value) {
		if(is_int($value) || is_float($value)) {
			return true;
		}
		elseif(!is_string($value)) {
			return false;
		}

		return (ctype_digit(str_replace('.', '', $value, $count)) && $count <= 1);
	}


	static public function match($pattern, $subject) {
		$subject = (string)$subject;
		preg_match($pattern, $subject, $matches);

		return isset($matches[0]) ? $matches[0]: '';
	}


	static public function get_query_string(array $data, string $separator = ';', string $operator = '='): string {
		$result = [];

		foreach($data as $key => $value) {
			$result[] = $key . $operator . $value;
		}

		return implode($separator, $result);
	}
}