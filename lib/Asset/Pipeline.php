<?php
namespace Asset;

require __DIR__ . '/functions.php';

class Pipeline
{
	static private $current_instance,
		$filters = array();
	private $paths,
		$dependencies,
		$files = array(),
		$file_added = false,
		$processed_files = array(),
		$main_file_name = 'application',
		$prefix,
		$registered_files = array();
	static public $cache = array();
	const DEPTH = 3;

	static public function cache($n, $v=null) {
		if (isset(self::$cache[$n]))$r=self::$cache[$n];else $r=null;
		if(null!==$v)self::$cache[$n]=$v;
		return $r;
	}

	public function __construct($paths, $prefix = '')
	{
		$this->prefix = $prefix;
		$this->paths = (array) $paths;
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
		$directories_hash = md5(serialize($this->paths));
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
/*
		if (!is_string($f))
			return '';

		$f = str_replace('//', '/', $f);

		foreach ($this->paths as $path)
		{
			foreach ($path['paths'] as $directory)
			{
				if (substr($f, 0, $len = strlen($directory)) == $directory)
				{
					$stripped = substr($f, $len);

					foreach ($this->directories_for_type as $type)
					{
						if (substr($stripped, 0, $len = strlen($type)) == $type)
						{
							$stripped = substr($stripped, $len + 1); //remove "/"
							return implode('/', array_slice(explode('/', $stripped), 0, -1));
						}
					}
					exit($f . ' starts with ' . $base_directory);
	
			}
		}

		/*
		foreach ($this->base_directories as $base_directory)
		{
			if (substr($f, 0, $len = strlen($base_directory)) == $base_directory)
			{
				$stripped = substr($f, $len);

				foreach ($this->directories_for_type as $type)
				{
					if (substr($stripped, 0, $len = strlen($type)) == $type)
					{
						$stripped = substr($stripped, $len + 1); //remove "/"
						return implode('/', array_slice(explode('/', $stripped), 0, -1));
					}
				}
				exit($f . ' starts with ' . $base_directory);
			}
		}*/
	}

	public function __invoke($t,$m=null,$v=array(),$f=false){return $this->process($t,$m,$v,$f);}
	public function process($type, $main_file = null, $vars = array(), $full = false)
	{
		if (self::$current_instance)
			throw new \RuntimeException('There is still a Pipeline instance running');
		self::$current_instance = $this;
		
		if ($main_file) //this if is why $this->main_file_name is used for File::__construct() below
			$this->main_file_name = $main_file;
		
		$content = (string) new File($this->main_file_name . '.' . $type, $vars);
		
		self::$current_instance = null;
		
		return $full ? array($this->registered_files[$type], $content) : $content;
	}
	
	public function getMainFile($type)
	{
		return $this->getFile($this->main_file_name, $type);
	}
	
	public function getBaseDirectories()
	{
		return $this->base_directories;
	}

	public function getDirectoriesFor($ext)
	{
		$directories = array();

		$type = $this->getTypeForExt($ext);

		foreach ($this->paths as $path)
		{
			$prefixes = isset($path['prefixes']) ? $path['prefixes'] : array();
			$prefix = isset($prefixes[$type]) ? $prefixes[$type] . '/' : '';
	
			foreach ($path['directories'] as $d)
				$directories[] = $d . $prefix;
		}
		
		return $directories;
	}

	public function getTypeForExt($ext)
	{
		switch ($named_type = $ext)
		{
			case 'png':
			case 'gif':
			case 'jpg':
			case 'jpeg':
				$named_type = 'img';
		}
		return $named_type;
	}

	/**
	 * if we have multiple filters, the mapping will be overrided
	 * so that we know last file's name :).
	 *
	 * could use an array tho, to keep track of every "step" (but not if no caching is used)
	 */
	public function registerFile($type, $from, $to)
	{
		if (!isset($this->registered_files[$type]))
			$this->registered_files[$type] = array();

		$this->registered_files[$type][$from] = $to;
	}

	public function getRegisteredFiles($type = null)
	{
		if (null === $type)
			return $this->registered_files;
		
		if (isset($this->registered_files[$type]))
			return $this->registered_files[$type];

		return array();
	}

	public function getNameAndExtension($name)
	{
		static $extensions = array('js', 'html', 'css', 'jpg', 'png', 'gif', 'txt');

		$filename_parts = explode('.', $name);
		$name_parts = array();

		/**
		 * NOTE:
		 * if at some moment, there's a need to allow files with extensions in name
		 * (ie sugar.js.min.js), just put the loop backward :
		 * read filters until in_array(), then slice parts to get $name
		 */

		foreach ($filename_parts as $i => $p)
		{
			//cannot be the name
			if ($i != 0 && in_array($p, $extensions))
			{
				$type = $p;
				break;
			}

			$name_parts[] = $p;
		}

		$name = implode('.', $name_parts);

		return array($name, $type, $i);
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
	
	public function getFilesUnder($name, $ext, $depth = -1)
	{
#		$cache_file = $this->getCacheDir() . 'dir_' . md5($dir) . '_' . $type . '_' . $depth . '.php';
#		if (false && file_exists($cache_file)) //disabled ATM
#			return include $cache_file;
#		else
#		{
		$files = array();

		$name = trim($name, './') . '/';
		$type = $this->getTypeForExt($ext);

		$directories = $canonical_directories = array();

		foreach ($this->paths as $path)
		{
			$prefixes = isset($path['prefixes']) ? $path['prefixes'] : array();
			$prefix = isset($prefixes[$type]) ? $prefixes[$type] . '/' : '';

			foreach ($path['directories'] as $directory)
			{
				$len = strlen($directory . $prefix);

				$directory_content = glob($directory . $prefix . $name . '*');
				foreach ($directory_content as $p)
				{ //file or dir
					if (isset($this->processed_files[$p]))
						continue;

					$canonical_path = trim(substr($p, $len), '/\\');
					if ($canonical_path[0] == '.')
						continue; //hidden file/dir

					if (is_dir($p))
					{
						if ($depth < 2 && $depth != -1)
							continue; //we reached depth limit

						$directories[] = $p; //not the canonical one, we want to list only this one
						$canonical_directories[] = $canonical_path;
					}
					else
					{ //@todo refactor
						if (false === strpos($canonical_path, '.'))
							continue; //ie LICENSE files (rofl)

						list($canonical, $extension) = explode('.', basename($canonical_path));
						if ($extension != $ext)
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
			}
		}

		if ($directories)
		{
			foreach ($directories as $i => $d)
			{
				$canonical_dir = $canonical_directories[$i];
				$len = strlen($d);

				$directory_content = glob($e=$d . '/*');
				foreach ($directory_content as $file)
				{
					$canonical = $canonical_dir . substr($file, $len); //$len + 1 to remove prefix "/"

					if (isset($this->processed_files[$canonical]))
						continue;

					if ($canonical[0] == '.')
						continue;

					if (is_dir($file))
					{ //not yet working :( - too lazy to refactor this part
					  //basically all you need is to extract this to a separate function			
						vdump($directories, $e, $directory_content, $file);
					}
					else
					{
						list($canonical, $extension) = explode('.', basename($canonical));
						if ($extension != $ext)
							continue;

						$canonical = trim($canonical, '/'.DIRECTORY_SEPARATOR);
						$full_path = $canonical_dir . '/' . $canonical;

						if (isset($this->processed_files[$full_path]))
							continue;

						$this->processed_files[$full_path] = true;
						$files[] = $full_path;
					}
				}
			}
		}

		return $files;
/*

			if ($directories && $depth - 1)
			{ //$depth is either -1 or >0
				foreach ($directories as $directory)
				{
					$files_under = $this->getFilesUnder($directory, $type, -1 == $depth ? -1 : $depth - 1);
					$files = array_merge($files, $files_under);
				}
			}
		}*/

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
	private function findFile($name, $ext)
	{
		$type = $this->getTypeForExt($ext);

		if (isset($this->files[$type][$name]) && file_exists($file = $this->files[$type][$name]))
			return $file;

		foreach ($this->paths as $path)
		{
			$prefixes = isset($path['prefixes']) ? $path['prefixes'] : array();
			$prefix = isset($prefixes[$type]) ? $prefixes[$type] . '/' : '';

			foreach ($path['directories'] as $directory)
			{
				$files = glob($e=trim($directory, '/') . '/' . $prefix . substr($name . '.' . $ext, 0, -1) . '*');

				if ($files)
				{
					$this->files[$type][$name] = $files[0];
					$this->file_added = true;

					return $files[0];
				}
			}
		}
		
		return null;
	}
	
	public function hasDirectory($name)
	{
		return isset($this->directories[$name]);
	}
	
	/**
	 * first-depth find. only returns 1 result
	 */
	public function getDirectory($name, $ext)
	{
		if ('' === $name = '/' . trim($name, '/.') . '/')
			return true;

		$type = $this->getTypeForExt($ext);

		foreach ($this->paths as $path)
		{
			$prefixes = isset($path['prefixes']) ? $path['prefixes'] : array();
			$prefix = isset($prefixes[$type]) ? $prefixes[$type] . '/' : '';
			
			foreach ($path['directories'] as $directory)
			{
				if (file_exists($directory) && is_dir($directory))
					return $directory;
			}
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