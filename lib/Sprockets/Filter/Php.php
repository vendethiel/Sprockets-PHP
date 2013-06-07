<?php
namespace Sprockets\Filter;

/**
 * PHP filter
 */
class Php extends Base
{
	public function __invoke($content, $file, $dir, $vars)
	{
		if (!file_exists($path = $this->getCacheDir($file, __CLASS__) . md5($content)))
			file_put_contents($path, $content);
		
		extract($vars);
		ob_start();
		require $path;
		return ob_get_clean();
	}
}