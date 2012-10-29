<?php
namespace Asset\Filter\Minifier;

/**
 * @todo the way the asset pipeline works ATM is that
 *  each files comes with its related content (directives)
 *  in order for the view_pipeline to work
 *  however this forbids the true use of source maps
 *  because it'll map to the big file.
 */
class Js extends Base
{
	public function __invoke($content)
	{
		//@todo md5 thousands of lines is stupid, what can I do ?
		$cache_file = $this->getCacheDir(md5($content) . '|js', __CLASS__);
		if (file_exists($js_cache_file = str_replace('.js', '.minified.js', $cache_file)))
			return file_get_contents($js_cache_file);

		if (!file_exists($cache_file))
			file_put_contents($cache_file, $content);

		$source_map = $cache_file . '.map';

		$out = $this->processNode("uglify-js2/bin/uglifyjs2 $cache_file -o $js_cache_file --source-map $source_map");

		if (!file_exists($js_cache_file))
		{
			echo "UglifyJS2 Minification Error<pre>" . str_replace($cache_file, $file, $out) . "</pre>";

			@unlink($log);
			exit;
		}

		return file_get_contents($js_cache_file);
	}
}