<?php
namespace Filter;

/**
 * CoffeeScript filter
 */
class Coffee implements iFilter
{
	public function __construct()
	{
		$previous_error_reporting = error_reporting();
		error_reporting(E_ALL);
		
		\CoffeeScript\Compiler::compile(''); //force class loading
		
		error_reporting($previous_error_reporting);
	}

	public function __invoke($content, $file, $vars)
	{
		\CoffeeScript\Compiler::compile($content, array('file' => $file));
	}
}