<?php
namespace Sprockets\Filter;

use Sprockets\Exception;

/**
 * LiveScript filter
 */
class Ls extends Base
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

		$opts = array('LiveScript/bin/livescript', '-c' . ($this->bare ? 'b' : ''), $cache_file);

		$out = $this->processNode($opts);

		if (!file_exists($js_cache_file))
		{
			throw new Exception\Filter("LiveScript Compilation Error<pre>" .
			 str_replace($cache_file, $file, $out) . "</pre>");
		}

		return file_get_contents($js_cache_file);
	}
}