<?php
namespace Asset\Filter;

use Asset\Pipeline;

/**
 * LESS filter
 */
class Less extends Base
{
	private $parser;
	
	private function getParser()
	{
		$this->parser = new \lessc;

		foreach (Pipeline::getCurrentInstance()->getDirectoriesFor('css') as $dir)
			$this->parser->addImportDir($dir);
		
		return $this->parser;
	}
	
	public function __invoke($content, $file, $dir, $vars)
	{
		return $this->getParser()->parse($content);
	}
}