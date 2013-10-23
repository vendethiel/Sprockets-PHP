<?php
namespace Sprockets\Filter;

/**
 * LESS filter
 */
class Less extends Base
{
	private $parser;
	
	private function getParser()
	{
		$this->parser = new \lessc;

		foreach ($this->pipeline->getLocator()->getDirectoriesFor('css') as $dir)
			$this->parser->addImportDir($dir);


		return $this->parser;
	}
	
	public function __invoke($content, $file, $dir, $vars)
	{
		return $this->getParser()->parse($content);
	}
}