<?php
namespace Filter;

use Asset\Pipeline;

/**
 * LESS filter
 */
class Less implements iFilter
{
	private $parser;
	
	private function getParser()
	{
		$this->parser = new \lessc;

		foreach (Pipeline::getCurrentInstance()->getBaseDirectories() as $dir)
			$this->parser->addImportDir($dir);
	}
	
	public function __invoke($content, $file, $vars)
	{
		return $this->getParser()->parse($content);
	}
}