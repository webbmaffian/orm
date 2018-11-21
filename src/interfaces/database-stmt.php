<?php

namespace Webbmaffian\ORM\Interfaces;

interface Database_Stmt {
	public function execute();

	public function get_query();
}