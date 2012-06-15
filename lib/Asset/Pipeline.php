<?php
namespace Asset;

class Pipeline
{
	static private $current_instance, $filters = array();
	private $base_directories, $files, $directories, $processed_files = array(), $dependencies;
	const DEPTH = -1;

	public function __construct($base_directories)
	{
		$this->base_directories = $base_directories;
		$this->listFilesAndDirectories();
		
	}

	static public function getCurrentInstance()
	{
		if (!self::$current_instance)
			throw new \RuntimeException('There is no Pipeline instance running');
			
		return self::$current_instance;
	}

	public function __invoke($type) { return $this->process($type); }
	public function process($type)
	{
		if (self::$current_instance)
			throw new \RuntimeException('There is still a Pipeline instance running');
		self::$current_instance = $this;
		
		return (string) new File('application.' . $type);
		
		self::$current_instance = null;
	}
	public function getMainFile($type)
	{
		return $this->getFile('application', $type);
	}
	

	public function hasFile($name, $type)
	{
		return isset($this->files[$type][$name]);
	}
	public function getFile($name, $type)
	{
		if (isset($this->files[$type][$name]))
			return $this->files[$type][$name];
		
		throw new Exception\FileNotFound($name, $type);
	}
	public function hasDirectory($name)
	{
		return isset($this->directories[$name]);
	}
	public function getDirectory($name)
	{
		if ('' === trim($name, '/.'))
			return true;

		if (isset($this->directories[$name]))
			return $this->directories[$name];
		
		throw new Exception\DirectoryNotFound($name);
	}
	public function hasProcessedFile($file)
	{
		if (isset($this->processed_files[$file]))
			return true;

		$this->processed_files[$file] = true;
	}
	
	public function getFilesUnder($directory, $type, $depth_limit = -1)
	{
		$files = array();

		if ($directory == '.')
			$directory = '';
		$directory_length = strlen($directory);

		foreach ($this->files[$type] as $name => $path)
		{
			if (isset($this->processed_files[$path]))
				continue;

			if (substr($name, 0, $directory_length) == $directory)
			{ //it starts with the right directory
				$relative_path = trim(substr($name, $directory_length), '/');
				$depth = count(explode('/', $relative_path));
				
				if (-1 != $depth_limit && $depth > $depth_limit)
					//it's not too far
					continue;

				$files[] = $name;
			}
		}

		return $files;
	}

	private function listFilesAndDirectories()
	{
		$files = array();
		$directories = array();
		
		$flags = \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS;
		foreach ($this->base_directories as $base_directory)
		{
			$base_directory_length = strlen($base_directory);

			$it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base_directory . '/', $flags),
			 \RecursiveIteratorIterator::CHILD_FIRST); //include directories
			if (self::DEPTH != -1)
				$it->setMaxDepth(self::DEPTH);

			while ($it->valid())
			{
				if ($it->isLink())
				{
					$it->next();
					continue;
				}

				$name = ltrim(substr($it->key(), $base_directory_length), '/');
				$path = $base_directory . '/' . $name;

				if ($it->isDir())
					$directories[$name] = $path;
				else
				{
					$name_parts = explode('.', $name);
					$name = $name_parts[0];
					$type = $name_parts[1];

					$files[$type][$name] = $path;
				}

				$it->next();
			}
		}
		
		$this->files = $files;
		$this->directories = $directories;
	}
	
	public function addDependency($path)
	{
		if (null === $this->dependencies)
			$this->dependencies = array();
		else if (!isset($this->dependencies[$path]))
			//in order to not register the first file
			$this->dependencies[$path] = true;
	}
	public function getDependencies()
	{
		return array_keys($this->dependencies);
	}
	public function getDependenciesFileContent()
	{
		$hash = array();
		
		foreach ($this->getDependencies() as $dependency)
			$hash[] = $dependency . ':' . filemtime($dependency);
			
		return implode("\n", $hash);
	}
	
	public function applyFilter($content, $filter, $file)
	{
		$filter = $this->getFilter($filter);
		$filter($content, $file);
	}
	private function getFilter($name)
	{
		if (!isset($this->filters[$name]))
		{
			$class = 'Filter\\' . $name;
			$this->filters[$name] = new $class;
		}
		
		return $this->filters[$name];
	}
}