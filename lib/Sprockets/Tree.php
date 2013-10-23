<?php
namespace Sprockets;

class Tree
{
	private $name, $type, $path, $vars = array();
	const DEPTH = -1;

	public function __construct($name, $type, $vars)
	{
		$this->name = $name;
		$this->type = $type;
		$this->vars = $vars;

		$this->locator = Pipeline::getCurrentInstance()->getLocator();

		$this->path = $this->locator->getDirectory($name, $type);
	}
	
	protected function getFilesList()
	{
		//use static::DEPTH for class Directory
		return $this->locator->getFilesUnder($this->name, $this->type, static::DEPTH);
	}
	
	public function process()
	{
		$content = ' ';

		//create an instance of File in order to parse other dependencies
		foreach ($this->getFilesList() as $file)
			$content .= (string) new File($file . '.' . $this->type, $this->vars);
		
		return $content;
	}

	public function __toString()
	{ try {
		return $this->process();	
	} catch (\Exception $e) {
		vdump($e);
	}
	}
}