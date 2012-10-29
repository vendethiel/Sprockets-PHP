<?php
namespace Asset\Filter;

use \Asset\Pipeline;

define('NODE_MODULES_PATH', __DIR__ . '/../../../node_modules/');

abstract class Base
{
	public function getCacheDir($file = '', $class = '')
	{
		$class = str_replace('\\', '__', $class);
		$file = $file == '' ? '' : $class . '_' . str_replace('.', '_', basename($file));
		$file = str_replace('|', '.', $file);

		return Pipeline::getCacheDirectory() . $file;
	}

	public function processNode($cmd)
	{
	    $log = $this->getCacheDir('node_log');
	    @unlink($log);

	    $script = 'node ' . NODE_MODULES_PATH . $cmd;
	    exec("$script > $log 2>&1", $out); //2>&1 redirects stderr to stdout

	    return file_get_contents($log);
	}
}