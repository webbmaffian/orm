<?php

namespace Webbmaffian\ORM\Helpers;

class Helper {
	
	static public function is_assoc($arr) {
		if(empty($arr)) return false;
		
		if(!is_array($arr)) throw new Problem('Input must be of type Array');

		$keys = array_keys($arr);
		if(is_string($keys[0])) return true;

		return false;
	}


	static public function match($pattern, $subject) {
		$subject = (string)$subject;
		preg_match($pattern, $subject, $matches);

		return isset($matches[0]) ? $matches[0]: '';
	}
}