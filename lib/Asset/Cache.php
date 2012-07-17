<?php
namespace Asset;

class Cache
{
	private $pipeline, $type, $options, $hash, $processed = false;

	public function __construct($pipeline, $type, $vars = array(), array $options = array())
	{
		$this->pipeline = $pipeline;
		$this->type = $type;
		$this->vars = $vars;
		$this->options = array_merge(array(
			'cache_directory' => 'cache/',
		), $options);
	}
	
	private function getDependenciesFilename()
	{
		return $this->options['cache_directory'] . 'dependencies_' . $this->type . '.txt';
	}
    
	private function getFilename()
	{
		if (!$this->hash)
			throw new \RuntimeException('Cache::getFilename has been called before dependencies were resolved');
	
		return str_replace('dependencies', 'file_' . $this->hash, $this->getDependenciesFilename()) . '.' . $this->type;
	}
	
	private function isFresh()
	{
		$cache_directory = $this->options['cache_directory'];
	
		if (!file_exists($cache_directory))
			mkdir($cache_directory);
		else if (!is_dir($cache_directory))
			throw new \InvalidArgumentException(sprintf('Cache directory "%s" is not a valid directory', $cache_directory));
	
		$path = $this->getDependenciesFilename();
		if (!file_exists($path))
			return false;
		
		$dependencies_file = file_get_contents($path);
		foreach (explode("\n", $dependencies_file) as $line)
		{ //for each dependency, check its state
			list($file, $mtime) = explode(':', $line);
			
			if (!file_exists($file))
				return false;
			if (filemtime($file) != $mtime)
				return false;
		}
		$this->hash = md5($dependencies_file);

		//this file_exists call will allow auto-refresh of the file if the naming strategy has changed
		return file_exists($this->getFilename());
	}
    
	private function write()
	{
		$pipeline = $this->pipeline; //__invoke won't with "$this->pipeline()"
	
		$content = $pipeline($this->type, null, $this->vars);
		$this->writeDependenciesFile();
		file_put_contents($this->getFilename(), $content);
	}
    
	private function writeDependenciesFile()
	{
		//depend on the main file (application.*) itself
		$this->pipeline->addDependency($this->pipeline->getMainFile($this->type));
		
		$content = $this->pipeline->getDependenciesFileContent();

		$this->hash = md5($content);
		file_put_contents($this->getDependenciesFilename(), $content);
	}
	
	public function process()
	{
		if ($this->processed)
			return $this->getFilename();
		$this->processed = true;
	
		if (!$this->isFresh())
			$this->write();

		return $this->getFilename();
	}
    
	public function __toString()
	{
		$this->process();
		
		return $this->getFilename();
	}
    
	public function getContent()
	{
		$this->process();
		
		return file_get_contents($this->getFilename());
	}
}