<?php
namespace Asset\Filter;

use \Asset\Pipeline;

if (!defined('NODE_BINARY'))
	define('NODE_BINARY', 'node'); //usr/bin/node or "C:/Program Files (x86)/nodejs/node"
if (!defined('NODE_MODULES_PATH'))
	define('NODE_MODULES_PATH', __DIR__ . '/../../../node_modules/');

abstract class Base
{
	protected $pipeline, $locator;

	public function setPipeline($pipeline)
	{
		$this->pipeline = $pipeline;
		$this->locator = $pipeline->getLocator();
	}

	public function getCacheDir($file = '', $class = '')
	{
		$class = str_replace('\\', '__', $class);
		$file = $file == '' ? '' : $class . '_' . str_replace('.', '_', basename($file));
		$file = str_replace('|', '.', $file);

		return Pipeline::getCacheDirectory() . $file;
	}

	protected function registerFile($name, $to)
	{
		list($from, $type) = $this->locator->getNameAndExtension($name);
		$this->pipeline->registerFile($type, $from, $to);
	}

	protected function processNode($cmd)
	{
	    $log = $this->getCacheDir('node_log');
	    @unlink($log);

	    $cmd[0] = NODE_MODULES_PATH . $cmd[0];
	    $script = implode(' ', array_map(function ($it) { return '"' . $it . '"'; },
	     array_merge(array(NODE_BINARY), $cmd)));
	    exec("$script > \"$log\" 2>&1", $out); //2>&1 redirects stderr to stdout

	    return file_exists($log) ? file_get_contents($log) : 'Failing command : ' . $e;
	}
}