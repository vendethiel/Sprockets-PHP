<?php
namespace Asset\Filter;

/**
 * CoffeeScript filter
 */
class Coffee extends Base
{
	public function __invoke($content, $file, $dir, $vars)
	{
		$cache_file = $this->getCacheDir('coffee_' . md5($content), __CLASS__);

		if (file_exists($cache_file))
			return file_get_contents($cache_file);

		$previous_error_reporting = error_reporting();
		error_reporting(E_ERROR);

		$script = \CoffeeScript\Compiler::compile($content, array('file' => $file));
		file_put_contents($cache_file, $script);

		error_reporting($previous_error_reporting);
		
		return $script;
	}
}