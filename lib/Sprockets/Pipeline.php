<?php
namespace Sprockets;

use Filter\iFilter;

require __DIR__ . '/functions.php';

class Pipeline
{
	static private $current_instance,
		$filters = array();

	private $extensions,
		$dependencies,
		$main_file_name = 'application',
		$prefix,
		$registered_files = array(),
		$options = array();

	const DEPTH = 3;

	public function __construct($paths, $prefix = '')
	{
		$this->prefix = $prefix;

		$paths = $this->setOptions($paths);

		$this->locator = new Locator($this, (array) $paths, $prefix);
	}

	/**
	 * Alias for {@link process()}
	 *
	 * @api
	 */
	public function __invoke($t,$m=null,$v=array(),$f=false){return $this->process($t,$m,$v,$f);}

	/**
	 * Runs the pipeline
	 *
	 * @param string $type asset type
	 * @param string $main_file main file, "application.$type" by default
	 * @param array $vars context for the pipeline (vars you pass to templates)
	 * @param bool $full True if you want an array containing file list and content
	 *
	 * @return string|array string unless $full
	 *
	 * @api
	 */
	public function process($type, $main_file = null, $vars = array(), $full = false)
	{
		if (self::$current_instance)
			throw new \RuntimeException('There is still a Pipeline instance running');
		self::$current_instance = $this;
		
		if ($main_file) //this if is why $this->main_file_name is used for File::__construct() below
			$this->main_file_name = $main_file;
		
		$this->registered_files[$type] = array();

		$content = (string) new File($this->main_file_name . '.' . $type, $vars);
		
		self::$current_instance = null;
		
		return $full ? array($this->registered_files[$type], $content) : $content;
	}

	/**
	 * set options (destructively) or defaults
	 */
	public function setOptions(array $options)
	{
		$base_options = array(
			'NODE_PATH' => 'node',
			'NPM_PATH' => __DIR__ . '/../../node_modules/',
			'CACHE_DIRECTORY' => 'cache/',
		);

		foreach ($base_options as $key => $default)
		{
			if (isset($options[$key]))
			{
				$this->options[$key] = $options[$key];
				unset($options[$key]);
			}
			else
				$this->options[$key] = $default;
		}

		return $options;
	}

	/**
	 * fetches an option
	 */
	public function getOption($name)
	{
		return $this->options[$name];
	}

	/**
	 * registers a special extension
	 *
	 * @example registerFilter('md', 'Sprockets\Filter\Markdown');
	 *
	 * @param string $ext Extension
	 * @param string $class Filter class
	 *
	 * @api
	 */
	public function registerFilter($ext, $class)
	{
		$this->extensions[strtolower($ext)] = $class;
	}

	public function getCacheDirectory()
	{
		$directory = $this->getOption('CACHE_DIRECTORY') . 'assets/';

		if (!file_exists($directory))
			mkdir($directory, 0777, true);
		
		return $directory;
	}

	public function getLocator()
	{
		return $this->locator;
	}

	public function getPrefix()
	{
		return $this->prefix;
	}

	/**
	 * returns the main file for a certain type
	 * proxies to the Locator
	 *
	 * @param string $type asset type
	 */
	public function getMainFile($type)
	{
		return $this->locator->getFile($this->main_file_name, $type);
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

	/**
	 * auto-vivificates $this->registered_files (shouldn't be needed)
	 *
	 * @param string $type file type or null for the whole array
	 */
	public function getRegisteredFiles($type = null)
	{
		if (null === $type)
			return $this->registered_files;
		
		if (isset($this->registered_files[$type]))
			return $this->registered_files[$type];

		return array();
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
	 * fitler singleton
	 *
	 * @param string $name filter name
	 *
	 * @return string Filter\iFilter
	 */
	public function getFilter($name)
	{
		if (!isset(self::$filters[$name]))
		{
			$class = isset($this->extensions[$name]) ? $this->extensions[$name] : 'Sprockets\Filter\\' . ucfirst($name);
			self::$filters[$name] = new $class;
			self::$filters[$name]->setPipeline($this);
		}
		
		return self::$filters[$name];
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
