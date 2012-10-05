<?php
namespace Filter;

use \Asset\Pipeline;
use \Asset\File;

class Scss implements iFilter
{
	private $parser;

	public function __construct()
	{
		$previous_error_reporting = error_reporting();
		error_reporting(E_ERROR);

		$this->parser = new \SassParser(array('syntax' => \SassFile::SCSS));

		error_reporting($previous_error_reporting);
	}
	
	public function __invoke($content, $file, $dir, $vars)
	{
		$content = preg_replace_callback('/@import\s+["\']([a-z0-9\/]+)["\']/i', function ($match) use ($dir)
		{
			$pipeline = Pipeline::getCurrentInstance();

			if ($match[1] == '/')
				$filename = $match;
			else
				$filename = $dir . '/' . $match[1];

			if ($pipeline->hasFile($filename, 'css'))
				$file = new File($filename . '.css');
			else if ($pipeline->hasFile($index_file = $filename . '/index', 'css'))
				$file = new File($index_file . '.css');
			else
				throw new Exception\FileNotFound($file, 'css');


			return '@import "' . str_replace('//', '/', $file->getFilepath()) . '"';
		}, $content);

		$p = Pipeline::cache('dir', $dir);

		$content = $this->parser->toCss($content, false);

		Pipeline::$cache['dir'] = $dir;

		return $content;
	}
}