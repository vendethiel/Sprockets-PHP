<?php
namespace Sprockets;

class Locator
{
	static private $files = null,
		$file_added = false;
	private $pipeline,
			$paths,
			$prefix,
			$processed_files = array(),
			$default_ext;

	public function __construct($pipeline, $paths, $prefix)
	{
		$this->pipeline = $pipeline;
		$this->paths = $paths;
		$this->prefix = $prefix;

		self::$file_added = false;
		if (null === self::$files)
			self::$files = array();
		
		if (!isset(self::$files[$prefix]))
		{
			if (file_exists($filelist = $this->getFileListName()))
				self::$files[$prefix] = include $filelist;
			else
				self::$files[$prefix] = array();
		}
	}

	// Calling file_put_contents within a destructor will cause the file to be written in SERVER_ROOT...
	public function save()
	{
		if (self::$file_added)
			file_put_contents($this->getFileListName(), '<?php return ' . var_export(self::$files[$this->prefix], true) . ';');

		self::$file_added = false;
	}

	public function getFileListName()
	{
		return $this->pipeline->getCacheDirectory() . 'filelist_' . $this->prefix . '.php';
	}

	public function skipFile($file)
	{
		$this->processed_files[$file] = true;
	}

	public function hasProcessedFile($file)
	{
		if (isset($this->processed_files[$file]))
			return true;

		$this->processed_files[$file] = true;
	}

	public function getPathsHash()
	{
		return md5(serialize($this->paths));
	}

	/**
	 * returns the named type of an extension
	 * ie png, gif, jpg and jpeg are images, no need to have separate folders
	 * same applies to otf and ttf, they're fonts
	 *
	 * @todo use some kind of array ? That'd maybe hurt performances (for real ? prolly not :p)
	 *       but I'd allow to dry it {@see getNameAndExtension}
	 *
	 * @var string $ext file's extension
	 *
	 * @return string ext's named type, or extension itself
	 */
	public function getTypeForExt($ext)
	{
		switch ($ext)
		{
			case 'png':
			case 'gif':
			case 'jpg':
			case 'jpeg':
				return 'img';

			case 'otf':
			case 'ttf':
				return 'font';
		}
		return $ext;
	}

	/**
	 * reads name and extension for a file name
	 *
	 * @example getNameAndExtension('jQuery.datatables.js.coffee') => ['jQuery.datatables', 'js', 2]
	 * @todo I'd like to avoid hardcoding every single extension I may need at some point.
	 *       the easiest must be if (!$type) $type = $filename_parts[1];
	 */
	public function getNameAndExtension($name)
	{
		static $extensions = array('js', 'html', 'css', 'txt',
			'png', 'gif', 'jpg', 'jpeg',
			'otf', 'ttf');

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

		if (!isset($type))
			$type = $filename_parts[1];

		return array($name, $type, $i);
	}


#	public function restoreExtension()
#	{
#		array_pop($this->extensions);
#	}
	public function setDefaultExtension($ext)
	{
#		if ($this->ext)
#			array_unshift($this->extensions, $this->ext);
		$this->default_ext = $ext;
	}


	public function hasFile($name, $type)
	{
		return !!$this->findFile($name, $type);
	}
	
	public function getFile($name, $type)
	{
		if (isset(self::$files[$this->prefix][$type][$name])
		 && file_exists($file = self::$files[$this->prefix][$type][$name]))
			return $file;

		if ($file = $this->findFile($name, $type))
			return $file;
		
		throw new Exception\FileNotFound($name, $type);
	}
	
	/**
	 * /vomit
	 */
	public function getFilesUnder($name, $ext, $depth = -1)
	{
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
				if (!$directory_content)
					continue; // MAY return false under unknown circumstances (...)

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
	}

	public function resolveFile($name)
	{
		list(,$ext) = $this->getNameAndExtension($name);
		$type = $this->getTypeForExt($ext);

		foreach ($this->paths as $path)
		{
			$prefixes = isset($path['prefixes']) ? $path['prefixes'] : array();
			$prefix = isset($prefixes[$type]) ? $prefixes[$type] . '/' : '';

			foreach ($path['directories'] as $directory)
			{
				$directory .= $prefix;
				$len = strlen($directory);

				if (substr($name, 0, $len) == $directory)
					return substr($name, $len, strpos($name, '.')-$len);
			}
		}

		return null;
	}
	
	/**
	 * finds a file, whether it has many extensions or not
	 * `findFile('xe', 'css')` will find 'xs.css.less'
	 *
	 * @param string $name
	 * @param string $ext
	 */
	private function findFile($name, $ext)
	{
		if (null === $ext)
			$ext = $this->default_ext;
		$type = $this->getTypeForExt($ext);

		if (isset(self::$files[$this->prefix][$type][$name])
		 && file_exists($file = self::$files[$this->prefix][$type][$name]))
			return $file;

		foreach ($this->paths as $path)
		{
			$prefixes = isset($path['prefixes']) ? $path['prefixes'] : array();
			$prefix = isset($prefixes[$type]) ? $prefixes[$type] . '/' : '';

			foreach ($path['directories'] as $directory)
			{
				$files = glob(rtrim($directory, '/') . '/' . $prefix . substr($name . '.' . $ext, 0, -1) . '*');

				if ($files)
				{
					self::$files[$this->prefix][$ext][$name] = $files[0];
					self::$file_added = true;

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

}