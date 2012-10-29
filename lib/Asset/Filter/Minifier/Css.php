<?php
namespace Asset\Filter\Minifier;

/**
 * @todo the way the asset pipeline works ATM is that
 *  each files comes with its related content (directives)
 *  in order for the view_pipeline to work
 *  however this forbids the true use of source maps
 *  because it'll map to the big file.
 */
class Css extends Base
{
	public function __invoke($content)
	{
		//@todo md5 thousands of lines is stupid, what can I do ?
		$cache_file = $this->getCacheDir(md5($content) . '|css', __CLASS__);
		if (file_exists($css_cache_file = str_replace('.css', '.minified.css', $cache_file)))
			return file_get_contents($css_cache_file);

		if (!file_exists($cache_file))
			file_put_contents($cache_file, $content);

		$out = $this->processNode("clean-css/bin/cleancss $cache_file -o $css_cache_file");

		if (!file_exists($css_cache_file))
		{
			echo "Clean-CSS Minification Error<pre>" . str_replace($cache_file, $file, $out) . "</pre>";

			@unlink($log);
			exit;
		}

		return file_get_contents($css_cache_file);
	}
}