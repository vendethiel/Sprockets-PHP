<?php
namespace Filter;

/**
 * LiveScript filter
 */
class Ls extends Base
{
	public function __invoke($content, $file, $dir, $vars)
	{
		$cache_file = $this->getCacheDir($file . '_' . $md5 = md5($content) . '|ls', __CLASS__);
		if (file_exists($js_cache_file = str_replace('.ls', '.js', $cache_file)))
			return file_get_contents($js_cache_file);

		if (!file_exists($cache_file))
			file_put_contents($cache_file, $content);

		$out = $this->processNode("LiveScript/bin/livescript -c $cache_file");

		if (!file_exists($js_cache_file))
		{
			echo "LiveScript Compilation Error<pre>" . str_replace($cache_file, $file, $out) . "</pre>";

			@unlink($log);
			exit;
		}

		return file_get_contents($js_cache_file);
	}
}