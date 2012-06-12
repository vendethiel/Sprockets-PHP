<?php
namespace Asset;

class Tree
{
	private $name;
	const DEPTH = -1;

	public function __construct($name, $type)
	{
		$this->name = $name;
		$this->type = $type;
		$this->path = Pipeline::getCurrentInstance()->getDirectory($name);
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
		{
			$content .= (string) new File($file . '.' . $this->type);
		}
		
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