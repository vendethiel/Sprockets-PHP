<?php
namespace Sprockets\Filter;

/**
 * CoffeeScript filter
 */
class Coffee extends Base
{
	protected $bare = false;

	public function __invoke($content, $file, $dir, $vars)
	{
		$cache_file = $this->getCacheDir($file . '_' . $md5 = md5($content) . '|ls', __CLASS__);

		$this->registerFile($file, $js_cache_file = str_replace('.ls', '.js', $cache_file));
		
		if (file_exists($js_cache_file))
			return file_get_contents($js_cache_file);

		if (!file_exists($cache_file))
			file_put_contents($cache_file, $content);

		$opts = array('coffee-script/bin/coffee', '-c' . ($this->bare ? 'b' : ''), $cache_file);

		$out = $this->processNode($opts);

		if (!file_exists($js_cache_file))
		{
			throw new Exception\Filter("CoffeeScript Compilation Error<pre>" .
			 str_replace($cache_file, $file, $out) . "</pre>");
		}

		return file_get_contents($js_cache_file);
	}
}