<?php
namespace Asset;

require __DIR__ . '/functions.php';

class Pipeline
{
	static private $current_instance,
		$filters = array();
	private $base_directories,
		$directories_for_type,
		$dependencies,
		$files = array(),
		$file_added = false,
		$processed_files = array(),
		$main_file_name = 'application',
		$prefix;
	static public $cache = array();
	const DEPTH = 3;

	static public function cache($n, $v=null) {
		if (isset(self::$cache[$n]))$r=self::$cache[$n];else $r=null;
		if(null!==$v)self::$cache[$n]=$v;
		return $r;
	}

	public function __construct($base_directories, $directories_for_type = array(), $prefix = '')
	{
		$this->prefix = $prefix;
		$this->base_directories = (array) $base_directories;
		$this->directories_for_type = $directories_for_type;
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

	public function getPrefix()
	{
		return $this->prefix;
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

	public function resolveDir($f)
	{
		if (!is_string($f))
			return '';

		$f = str_replace('//', '/', $f);

		foreach ($this->base_directories as $base_directory)
		{
			if (substr($f, 0, $len = strlen($base_directory)) == $base_directory)
			{
				$stripped = substr($f, $len);

				foreach ($this->directories_for_type as $type)
				{
					if (substr($stripped, 0, $len = strlen($type)) == $type)
					{
						$stripped = substr($stripped, $len + 1); //renome "/"
						return implode('/', array_slice(explode('/', $stripped), 0, -1));
					}
				}
				exit($f . ' starts with ' . $base_directory);
			}
		}
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

	public function getDirectoriesFor($type)
	{
		$directories = array();

		$directory_for_type = $this->getDirectoryForType($type);

		foreach ($this->base_directories as $base_directory)
			$directories[] = $base_directory . $directory_for_type;
		
		return $directories;
	}

	public function getDirectoryForType($type)
	{
		switch ($named_type = $type)
		{
			case 'png':
			case 'gif':
			case 'jpg':
			case 'jpeg':
				$named_type = 'img';
		}

		return isset($this->directories_for_type[$named_type]) ?
		 '/' . $this->directories_for_type[$named_type] : '';
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
#		$cache_file = $this->getCacheDirectory() . 'directory_' . md5($directory) . '_' . $type . '_' . $depth . '.php';
#		if (false && file_exists($cache_file)) //disabled ATM
#			return include $cache_file;
#		else
#		{
		$files = array();
		
		$directory = '/' . trim($directory, './') . '/';
		$directory_for_type = $this->getDirectoryForType($type);

		foreach ($this->base_directories as $base_directory)
		{
			$directory_length = strlen($base_directory . $directory_for_type) + 1; //+1 for '/'

			$directory_files = glob($e = $base_directory . $directory_for_type . $directory . '*');

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
					$files[] = $full_path;
				}
			}

			if ($directories && $depth - 1)
			{ //$depth is either -1 or >0
				foreach ($directories as $directory)
				{
					$files_under = $this->getFilesUnder($directory, $type, -1 == $depth ? -1 : $depth - 1);
					$files = array_merge($files, $files_under);
				}
			}
		}

#		file_put_contents($cache_file, '<?php return ' . var_export($files, true) . ';');
		return $files;
#		}
	}
	
	/**
	 * finds a file, whether it has many extensions or not
	 * `findFile('xe', 'css')` will find 'xs.css.less'
	 *
	 * @param 
	 */
	private function findFile($name, $type)
	{
		if (isset($this->files[$type][$name]) && file_exists($file = $this->files[$type][$name]))
			return $file;
	
		$directory_for_type = $this->getDirectoryForType($type);

		foreach ($this->base_directories as $base_directory)
		{
			$files = glob($e=$base_directory . $directory_for_type . substr('/' . $name . '.' . $type, 0, -1) . '*');

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
	
	public function getDirectory($name, $type)
	{
		if ('' === $name = '/' . trim($name, '/.') . '/')
			return true;

		$directory_for_type = $this->getDirectoryForType($type);

		foreach ($this->base_directories as $base_directory)
		{
			$directory = $base_directory . $directory_for_type . $name;

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
	
	/**
	 * adds the $path to dependency list of $type
	 * using type-based dependencies to, for example, allow a css file to rely on a .png file
	 *
	 * @param string $type dependency type (application.$type)
	 * @param string $path file path (x.png, for example)
	 */
	public function addDependency($type, $path)
	{
		if (null === $this->dependencies)
			$this->dependencies = array();
		if (!isset($this->dependencies[$type]))
			$this->dependencies[$type] = array();
		
		if (!isset($this->dependencies[$type][$path]))
			//in order to not register the first file
			$this->dependencies[$type][$path] = true;
	}
	
	/**
	 * returns dependency list for the given $type
	 *
	 * @param string $type dependency type (application.$type)
	 *
	 * @return array files dependent for the type
	 */
	public function getDependencies($type)
	{
		return array_keys($this->dependencies[$type]);
	}

	/**
	 * returns dependency list formatted for storing
	 *
	 * @param string $type dependency type (application.$type)
	 *
	 * @return string file formatted "path:mtime\npath:..."
	 */
	public function getDependenciesFileContent($type)
	{
		$hash = array();
		
		foreach ($this->getDependencies($type) as $dependency)
			$hash[] = $dependency . ':' . filemtime($dependency);
			
		return implode("\n", $hash);
	}
	
	/**
	 * apply a filter
	 * used for singletonization of filters
	 *
	 * @param string $content content to apply filter on
	 * @param string $filter filter name
	 * @param string $file file name (for errors / cache naming)
	 * @param array $vars context
	 *
	 * @return string $content with $filter processed on
	 */
	public function applyFilter($content, $filter, $file, $dir, $vars)
	{
		$filter = $this->getFilter($filter);
		return $filter($content, $file, $dir, $vars);
	}
	
	/**
	 * fitler singleton
	 *
	 * @param string $name filter name
	 *
	 * @return string Filter\iFilter
	 */
	private function getFilter($name)
	{
		if (!isset(self::$filters[$name]))
		{
			$class = 'Asset\Filter\\' . ucfirst($name);
			self::$filters[$name] = new $class;
		}
		
		return self::$filters[$name];
	}

	/**
	 * singleton
	 *
	 * @return Pipeline current pipeline instance
	 */
	static public function getCurrentInstance()
	{
		if (!self::$current_instance)
			throw new \RuntimeException('There is no Pipeline instance running');
			
		return self::$current_instance;
	}
}