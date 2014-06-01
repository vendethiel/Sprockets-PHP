<?php
namespace Sprockets\Filter;

class Sass extends Scss
{
	public function __construct()
	{
		$previous_error_reporting = error_reporting();
		error_reporting(E_ERROR);

		$this->parser = new \SassParser;
		
		error_reporting($previous_error_reporting);
	}

	// __invoke inherited from \Sprockets\Filter\Scss
}