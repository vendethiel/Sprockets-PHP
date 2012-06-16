<?php
namespace Filter;

/**
 * PHP filter
 */
class Php extends Base
{
	public function __invoke($content, $file, $vars)
	{ //just plain bad :-)
		extract($vars);
	
		eval($content);
	}
}