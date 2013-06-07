<?php
namespace Sprockets\Filter;

/**
 * CoffeeScript filter
 */
class Coffee extends Base
{
	public function __invoke($content, $file, $dir, $vars)
	{
		$cache_file = $this->getCacheDir('coffee_' . md5($content) . '|js', __CLASS__);

		$this->registerFile($file, $cache_file);

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