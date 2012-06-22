<?php
namespace Filter;

/**
 * CoffeeScript filter
 */
class Coffee implements iFilter
{
	public function __invoke($content, $file, $vars)
	{
		$previous_error_reporting = error_reporting();
		error_reporting(E_ERROR);
	
		$script = \CoffeeScript\Compiler::compile($content, array('file' => $file));
		
		error_reporting($previous_error_reporting);
		
		return $script;
	}
}