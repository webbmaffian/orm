<?php
	namespace Webbmaffian\ORM\Helpers;

	class Sanitize {
		static public function key($key) {
			$key = str_replace(' ', '_', trim(strtolower($key)));
			
			return preg_replace('/[^a-z0-9_]+/', '', $key);
		}
	}