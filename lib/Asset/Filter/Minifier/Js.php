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
	public function __invoke($files, $content)
	{
		$cache_file = $this->getCacheDir($hash = md5(implode('*', $files)) . '|js', __CLASS__);
		$js_cache_file = str_replace('.js', '.minified.js', $cache_file);
		$source_map = str_replace('.js', '.js.map', $cache_file);

		$cache_dir = trim(str_replace('\\', '/', $this->getCacheDir()), '/');
//		vdump(, dirname(dirname(dirname(__DIR__))));

		if (file_exists($js_cache_file))
			return file_get_contents($js_cache_file);

		if (!file_exists($cache_file))
			file_put_contents($cache_file, $content);

#		$files_instruction = implode(' ', array_map(function ($file) { return '"' . $file . '"';}, array_values($files)));

#		$out = $this->processNode("uglify-js2/bin/uglifyjs2\" $files_instruction -o \"$js_cache_file\" --source-map \"$source_map\"");
		$out = $this->processNode("uglify-js2/bin/uglifyjs2\" \"$cache_file\" -o \"$js_cache_file\" ");
#			. "--source-map \"$source_map\" --source-map-root \"$cache_dir\"");

		if (!file_exists($js_cache_file))
		{
			echo "UglifyJS2 Minification Error<pre>" . str_replace($cache_file, $js_cache_file, $out) . "</pre>";

			@unlink($log);
			exit;
		}

		return file_get_contents(str_ireplace(array('c:/', 'c:\\'), 'file://C:/', $js_cache_file));
	}
}