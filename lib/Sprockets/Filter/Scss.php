<?php
namespace Sprockets\Filter;

use Sprockets\File;

class Scss extends Base
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
			if ($match[1] == '/')
				$filename = $match;
			else
				$filename = $dir . '/' . $match[1];

			if ($this->pipeline->hasFile($filename, 'css'))
				$file = new File($filename . '.css');
			else if ($this->pipeline->hasFile($index_file = $filename . '/index', 'css'))
				$file = new File($index_file . '.css');
			else
				throw new Exception\FileNotFound($file, 'css');

			return '@import "' . str_replace('//', '/', $file->getFilepath()) . '"';
		}, $content);

		$content = $this->parser->toCss($content, false);

		return $content;
	}
}