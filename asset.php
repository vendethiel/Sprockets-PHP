<?php
namespace Asset;

abstract class Base
{
	private $name, $type;

	public function __construct($name, $type)
	{
		$this->name = $name;
		$this->type = $type;
		
		$this->create();
	}
	
	protected function create() { }
	abstract public function process();
	public function __toString()
	{
		return $this->process();
	}
}


class Pipeline
{
	static private $current_instance;
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
}

class Cache
{
	private $pipeline, $type, $options, $hash, $processed = false;

	public function __construct($pipeline, $type, array $options = array())
	{
		$this->pipeline = $pipeline;
		$this->type = $type;
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
	
		return str_replace('dependencies', 'file_' . $this->hash, $this->getDependenciesFilename());
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
		$pipeline = $this->pipeline; //__invoke won't work otherwise
	
		$content = $pipeline($this->type);
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

class File
{
	private $filepath, $path, $directory, $name, $type, $filters;

	public function __construct($path)
	{
		$pipeline = Pipeline::getCurrentInstance();
	
		$this->path = $path;
		$this->directory = '.' === ($dirname = dirname($path)) ? '' : $dirname;
		
		$file = basename($path);
		$filename_parts = explode('.', $file);
		$this->name = $filename_parts[0];
		$this->type = $filename_parts[1];
		$this->filters = array_slice($filename_parts, 2);
		
		$this->path_with_simple_filename = ('' === $this->directory ? '' : $this->directory . '/') . $this->name;
		$this->filepath = $pipeline->getFile($this->path_with_simple_filename, $this->type);

		$pipeline->addDependency($this->filepath);
	}
	
	private function getProcessedContent()
	{
		$content = self::processFilters($this->filepath, $this->filters);
		$new_content = '';
		
		foreach (explode("\n", $content) as $line)
		{
			if (substr($line, 0, 3) == '//=' || substr($line, 0, 2) == '#=')
			{
				$directive = explode(' ', trim(substr($line, 3)));
				
				$function = $directive[0];
				$arguments = array_slice($directive, 1);
				
				$new_content .= call_user_func_array(array($this, pascalize($function) . 'Directive'), $arguments) . "\n";
			}
			else
				$new_content .= $line . "\n";
		}
		
		return $new_content;
	}	

	public function process()
	{
		if (Pipeline::getCurrentInstance()->hasProcessedFile($this->filepath))
			return; //hasProcessedFile will add it otherwise

		return $this->getProcessedContent();
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
	
	
	private function requireDirective($name)
	{
		$pipeline = Pipeline::getCurrentInstance();

		if ($pipeline->hasFile($file = $this->directory . $name, $this->type))
			return (string) new File($file . '.' . $this->type);
		else if ($pipeline->hasFile($file = $this->directory . $name . '/index', $this->type))
			return (string) new File($file . '.' . $this->type);
		else
			throw new Exception\FileNotFound($file, $type);
	}
	private function requireTreeDirective($name = '/')
	{
		return (string) new Tree($this->directory . $name, $this->type);
	}
	private function requireDirectoryDirective($name = '/')
	{
		return (string) new Directory($this->directory . $name, $this->type);
	}
	private function dependsOnDirective($name)
	{ //allows to depend on a file, even if this one isn't included
	}
	
	
	static private function processFilters($path, $filters)
	{
		return file_get_contents($path);
	}
}

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

class Directory extends Tree
{
	const DEPTH = 1;
}


namespace Asset\Exception;
use \Exception;

class Asset extends Exception
{ }

class AssetNotFound extends Asset
{ }

class FileNotFound extends AssetNotFound
{
	public function __construct($name, $type)
	{
		parent::__construct("The asset file '$name' of type '$type' wasn't found.\n" . parent::getTraceAsString());
	}
}
class DirectoryNotFound extends AssetNotFound
{
	public function __construct($name)
	{
		parent::__construct("The asset directory '$name' wasn't found.\n" . parent::getTraceAsString());
	}
}