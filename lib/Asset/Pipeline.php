<?php
namespace Asset;

class Pipeline
{
	static private $current_instance,
		$filters = array();
	private $base_directories,
		$dependencies,
		$files = array(),
		$file_added = false,
		$processed_files = array(),
		$main_file_name = 'application';
	const DEPTH = 3;

	public function __construct($base_directories)
	{
		$this->base_directories = (array) $base_directories;
		$this->readCache();
	}
	
	static public function getCacheDirectory()
	{
		//../../cache/
		$directory = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'cache/';
		
		if (!file_exists($directory))
			mkdir($directory);
		
		return $directory;
	}
	
	private function getFileListCache()
	{
		$directories_hash = md5(implode(';', $this->base_directories));
		return self::getCacheDirectory() . 'file_list_' . $directories_hash . '.php';
	}
	
	public function readCache()
	{
		if (file_exists($file = $this->getFileListCache()))
			$this->files = include $file;
	}

	public function __destruct()
	{
		if ($this->file_added)
			file_put_contents($this->getFileListCache(), '<?php return ' . var_export($this->files, true) . ';');
	}

	public function __invoke($t,$m=null,$v=array()){return $this->process($t,$m,$v);}
	public function process($type, $main_file = null, $vars = array())
	{
		if (self::$current_instance)
			throw new \RuntimeException('There is still a Pipeline instance running');
		self::$current_instance = $this;
		
		if ($main_file) //this if is why $this->main_file_name is used for File::__construct() below
			$this->main_file_name = $main_file;
		
		$content = (string) new File($this->main_file_name . '.' . $type, $vars);
		
		self::$current_instance = null;
		
		return $content;
	}
	
	public function getMainFile($type)
	{
		return $this->getFile($this->main_file_name, $type);
	}
	
	public function getBaseDirectories()
	{
		return $this->base_directories;
	}

	public function hasFile($name, $type)
	{
		return !!$this->findFile($name, $type);
	}
	
	public function getFile($name, $type)
	{
		if ($file = $this->findFile($name, $type))
			return $file;
		
		throw new Exception\FileNotFound($name, $type);
	}
	
	public function getFilesUnder($directory, $type, $depth = -1)
	{
		$files = array();
		
		$directory = '/' . trim($directory, './') . '/';
		
		foreach ($this->base_directories as $base_directory)
		{
			$directory_length = strlen($base_directory) + 1; //+1 for '/'
		
			$directory_files = glob($base_directory . $directory . '*');
			$found = false;
			$directories = array();
			foreach ($directory_files as $path)
			{
				if (isset($this->processed_files[$path]))
					continue;
			
				$canonical_path = trim(substr($path, $directory_length), '/\\');
				if ($canonical_path[0] == '.')
					continue; //skip that!
				
				if (is_dir($path))
				{
					if ($depth == 0)
						continue;

					$directories[] = $canonical_path;
				}
				else
				{
					list($canonical, $extension) = explode('.', basename($canonical_path));
					if ($extension != $type)
						continue;
					
					$canonical_dir = trim(dirname($canonical_path), '/'.DIRECTORY_SEPARATOR);
					$canonical = trim($canonical, '/'.DIRECTORY_SEPARATOR);
					$full_path = $canonical_dir . '/' . $canonical;
					
					if (isset($this->processed_files[$full_path]))
						continue;

					$this->processed_files[$full_path] = true;
					$found = true;
					$files[] = $full_path;
				}
			}
			if ($directories && $found && ($depth - 1))
			{ //$depth is either -1 or >0
			//$found: if we have a file with this extension in the directory.
			//		Else, there's no point in continuing
			//		you may need to add a file with the type to tell Sprockets-PHP to keep looking
				foreach ($directories as $directory)
				{
					$files_under = $this->getFilesUnder($directory, $type, -1 == $depth ? -1 : $depth - 1);
					$files = array_merge($files, $files_under);
				}
			}
		}
		
		return $files;
	}
	
	private function findFile($name, $type)
	{
		if (isset($this->files[$type][$name]))
			return $this->files[$type][$name];
	
		foreach ($this->base_directories as $base_directory)
		{
			$files = glob($base_directory . substr('/' . $name . '.' . $type, 0, -1) . '*');
			
			if ($files)
			{
				$this->files[$type][$name] = $files[0];
				$this->file_added = true;

				return $files[0];
			}
		}
		
		return null;
	}
	
	public function hasDirectory($name)
	{
		return isset($this->directories[$name]);
	}
	
	public function getDirectory($name)
	{
		if ('' === $name = '/' . trim($name, '/.') . '/')
			return true;

		foreach ($this->base_directories as $base_directory)
		{
			$directory = $base_directory . $name;

			if (file_exists($directory) && is_dir($directory))
				return $directory;
		}
		
		throw new Exception\DirectoryNotFound($name);
	}
	
	public function hasProcessedFile($file)
	{
		if (isset($this->processed_files[$file]))
			return true;

		$this->processed_files[$file] = true;
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
	
	public function applyFilter($content, $filter, $file, $vars)
	{
		$filter = $this->getFilter($filter);
		return $filter($content, $file, $vars);
	}
	
	private function getFilter($name)
	{
		if (!isset(self::$filters[$name]))
		{
			$class = 'Filter\\' . ucfirst($name);
			self::$filters[$name] = new $class;
		}
		
		return self::$filters[$name];
	}

	static public function getCurrentInstance()
	{
		if (!self::$current_instance)
			throw new \RuntimeException('There is no Pipeline instance running');
			
		return self::$current_instance;
	}
}