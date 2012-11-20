<?php
namespace Asset\Filter;

use \Asset\Pipeline;

if (!defined('NODE_BINARY'))
	define('NODE_BINARY', 'node'); //usr/bin/node or "C:/Program Files (x86)/nodejs/node"
if (!defined('NODE_MODULES_PATH'))
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

	protected function registerFile($name, $to)
	{
		$pipeline = Pipeline::getCurrentInstance();
		list($from, $type) = $pipeline->getNameAndExtension($name);
		$pipeline->registerFile($type, $from, $to);
	}

	protected function processNode($cmd)
	{
	    $log = $this->getCacheDir('node_log');
	    @unlink($log);

	    /*
	     * I believe the NODE_BINARY hack currently fails on windows
	     * for the sole reason that `C:/> /foo/bar.bat` fails
	     * @todo? when windows
	    $dir = dirname(NODE_BINARY);
	    $binary = basename(NODE_BINARY);
	    $script = 'cd "' . $dir . '"" && "' . $binary . '" ' . NODE...
	     */
	    $script = NODE_BINARY . ' "' . NODE_MODULES_PATH . $cmd;
	    exec($e = "$script > \"$log\" 2>&1", $out); //2>&1 redirects stderr to stdout

	    return file_exists($log) ? file_get_contents($log) : 'Failing command : ' . $e;
	}
}