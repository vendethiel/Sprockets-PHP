<?php
namespace Asset;

class Tree
{
	private $name, $type, $path, $vars = array();
	const DEPTH = -1;

	public function __construct($name, $type, $vars)
	{
		$this->name = $name;
		$this->type = $type;
		$this->vars = $vars;
		$this->path = Pipeline::getCurrentInstance()->getDirectory($name, $type);
	}
	
	protected function getFilesList()
	{
		//use static::DEPTH for class Directory
		return Pipeline::getCurrentInstance()->getFilesUnder($this->name, $this->type, static::DEPTH);
	}
	
	public function process()
	{
		$content = '';

		//create an instance of File in order to parse other dependencies
		foreach ($this->getFilesList() as $file)
			$content .= (string) new File($file . '.' . $this->type, $this->vars);
		
		return $content;
	}
	
	public function __toString()
	{
		try {
			return $this->process();
		} catch (Exception\Asset $e) {
			exit('Asset exception : ' . $e->getMessage());
		} catch (\Exception $e) {
			exit('External exception : ' . $e->getMessage());
		}
	}
}