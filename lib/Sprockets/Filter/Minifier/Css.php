<?php
namespace Sprockets\Filter\Minifier;

/**
 * node:clean-css
 */
class Css extends Base
{
	public function __invoke($files, $content)
	{
		//@todo md5 thousands of lines is stupid, what can I do ?
		$cache_file = $this->getCacheDir(md5($content) . '|css', __CLASS__);
		if (file_exists($css_cache_file = str_replace('.css', '.minified.css', $cache_file)))
			return file_get_contents($css_cache_file);

		if (!file_exists($cache_file))
			file_put_contents($cache_file, $content);

		$out = $this->processNode(array('clean-css/bin/cleancss', $cache_file, '-o', $css_cache_file));

		if (!file_exists($css_cache_file))
		{
			echo "Clean-CSS Minification Error<pre>" . str_replace($cache_file, $css_cache_file, $out) . "</pre>";

			@unlink($log);
			exit;
		}

		return file_get_contents($css_cache_file);
	}
}