<?php
namespace Filter;

/**
 * PHP filter
 */
class Php extends Base
{
	public function __invoke($content, $file)
	{ //just plain bad :-)
		eval($content);
	}
}