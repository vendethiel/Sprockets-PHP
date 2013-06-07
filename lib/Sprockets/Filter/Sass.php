<?php
namespace Sprockets\Filter;

class Sass extends Base
{
	private $parser;

	public function __construct()
	{
		$previous_error_reporting = error_reporting();
		error_reporting(E_ERROR);

		$this->parser = new \SassParser;
		
		error_reporting($previous_error_reporting);
	}
	
	public function __invoke($content, $file, $dir, $vars)
	{
		return $this->parser->toCss($content, false);
	}
}