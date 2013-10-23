<?php
namespace Sprockets\Filter;

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

		return $this->pipeline->getCacheDirectory() . $file;
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

		if (!$this->checkExec())
			exit('You need exec() enabled in PHP (php.ini, disable_functions).');

		$cmd[0] = $this->pipeline->getOption('NPM_PATH') . $cmd[0];
		$script = implode(' ', array_map(function ($arg) { return escapeshellarg($arg); },
		 array_merge(array($this->pipeline->getOption('NODE_PATH')), $cmd)));
		$script = escapeshellcmd($script);
		exec("$script > \"$log\" 2>&1"); //2>&1 redirects stderr to stdout

		if (file_exists($log))
			return file_get_contents($log);
		else
			return 'Failing command : ' . $script . "\n$log";
	}

	protected function checkExec()
	{
		$functions = str_replace(', ', ',', ini_get('disable_functions'));
		return !in_array('exec', explode(',', $functions));
	}
}