<?php
namespace Sprockets\Filter\Minifier;

/**
 * node:uglify-js2
 */
class Js extends Base
{
	public function __invoke($files, $content)
	{
		$cache_file = $this->getCacheDir($hash = md5(implode('*', $files)) . '|js', __CLASS__);
		$js_cache_file = str_replace('.js', '.minified.js', $cache_file);
#		$source_map = str_replace('.js', '.js.map', $cache_file);

		$cache_dir = trim(str_replace('\\', '/', $this->getCacheDir()), '/');

		if (file_exists($js_cache_file))
			return file_get_contents($js_cache_file);

		if (!file_exists($cache_file))
			file_put_contents($cache_file, $content);

#		$files_instruction = implode(' ', array_map(function ($file) { return '"' . $file . '"';}, array_values($files)));

		$out = $this->processNode(array('esmangle/bin/esmangle', $cache_file, '--output', $js_cache_file));

		if (!file_exists($js_cache_file))
		{
			echo "ESmangle Minification Error<pre>" . str_replace($cache_file, $js_cache_file, $out) . "</pre>";

			@unlink($log);
			exit;
		}

		return file_get_contents(str_ireplace(array('c:/', 'c:\\'), 'file://C:/', $js_cache_file));
	}
}