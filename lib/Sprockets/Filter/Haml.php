<?php
namespace Sprockets\Filter;

use \MtHaml;

class Haml extends Base
{
	private $parser;
	
	//lazy instanciation
	// (not instanciated if a cache file is found)
	private function getParser()
	{
		if (!$this->parser)
			$this->parser = new MtHaml\Environment('php');
		
		return $this->parser;
	}

	public function __invoke($content, $file, $dir, $vars)
	{
		if (!file_exists($path = $this->getCacheDir($file, __CLASS__) . md5($content)))
			file_put_contents($path, $this->getParser()->compileString($content, $file));

		extract($vars);
		ob_start();
		require $path;
		return ob_get_clean();
	}
}