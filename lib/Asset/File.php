<?php
namespace Asset;

class File
{
	private $filepath,
		$file,
		$full_filename,
		$path,
		$directory,
		$name,
		$type,
		$path_with_simple_filename,
		$filters,
		$vars = array();

	public function __construct($path, $vars = array())
	{
		$pipeline = Pipeline::getCurrentInstance();

		$this->path = trim(trim($path, '/'), '\\');
		$this->directory = '.' === ($dirname = dirname($path)) ? '' : $dirname;

		$this->vars = $vars;

		$this->file = basename($path);
		$filename_parts = explode('.', $this->file);
		if (!isset($filename_parts[1]))
			vdump($path, 'no filename parts 1');
		$this->name = $filename_parts[0];
		$this->type = $filename_parts[1];

		$this->path_with_simple_filename = ('' === $this->directory ? '' : $this->directory . '/') . $this->name;
		$this->filepath = $pipeline->getFile($this->path_with_simple_filename, $this->type);

		$full_filename = explode('/', $this->filepath);
		$this->full_filename = end($full_filename);
		$full_filename_parts = explode('.', $this->full_filename);
		$this->filters = array_reverse(array_slice($full_filename_parts, 2)); //['less', 'php'] => ['php', 'less']

		if (in_array($this->type, array('html', 'css', 'js')))
			$pipeline->addDependency($this->type, $this->filepath);
	}

	public function getName()
	{
		return $this->name;
	}

	public function getFullName()
	{
		return $this->file;
	}

	public function getFilepath()
	{
		return $this->filepath;
	}

	public function getPathWithSimpleFilename()
	{
		return $this->path_with_simple_filename;
	}

	public function getPathWithFullFilename()
	{
		return ('' === $this->directory ? '' : $this->directory . '/') . $this->full_filename;
	}

	private function getProcessedContent()
	{
		$filters = $this->filters;
		if (class_exists('Filter\\' . ucfirst($this->type)))
			$filters[] = $this->type;

		$content = self::processFilters($this->filepath, $this->directory, $filters, $this->vars);


		if ($this->type != 'js' && $this->type != 'css')
			return $content; //no directives

		return $this->processDirectives($content);
	}

	public function process()
	{
		if (Pipeline::getCurrentInstance()->hasProcessedFile($this->filepath))
			return ' '; //hasProcessedFile will add it otherwise

		return $this->getProcessedContent();
	}

	public function __toString()
	{
		try {
			$e= $this->process();
			if (empty($e) || !is_string($e))
				vdump($e, $this->getFilepath(), file_get_contents($this->getFilepath()));
			return $e;
		} catch (Exception\Asset $e) {
			exit('Asset exception (' . $this->getFilepath() . ') : ' . $e->getMessage());
		} catch (\Exception $e) {
			exit('External exception (' . $this->getFilepath() . ') : ' . $e->getMessage());
		}
	}


	private function requireDirective($name)
	{
		$pipeline = Pipeline::getCurrentInstance();

		if ($pipeline->hasFile($file = $this->directory . $name, $this->type))
			return (string) new File($file . '.' . $this->type, $this->vars);
		else if ($pipeline->hasFile($index_file = $this->directory . $name . '/index', $this->type))
			return (string) new File($index_file . '.' . $this->type, $this->vars);
		else
			throw new Exception\FileNotFound($file, $this->type);
	}

	private function requireTreeDirective($name = '/')
	{
		return (string) new Tree($this->directory . $name, $this->type, $this->vars);
	}

	private function requireDirectoryDirective($name = '/')
	{
		return (string) new Directory($this->directory . $name, $this->type, $this->vars);
	}

	private function dependsOnDirective($name)
	{ //allows to depend on a file, even if this one isn't included
		$pipeline = Pipeline::getCurrentInstance();

		if (strpos($name, '.') === false)
			$type = $this->type;
		else
		{ //depending on another asset type. In example, //= depends_on image.png
			$name_parts = explode('.', $name);
			$type = $name_parts[1]; //"style" "css" *filters
		}

		$pipeline->addDependency($this->type, $pipeline->getFile($name, $type));
	}

	private function processDirectives($content)
	{
		$new_content = '';

		foreach (explode("\n", $content) as $line)
		{
			if ((($this->type == 'js' || $this->type == 'css') && substr($line, 0, 3) == '//=') ||
			 ($this->type == 'js' && substr($line, 0, 2) == '#='))
			{
				$directive = explode(' ', trim(substr($line, 3)));

				$function = $directive[0];
				$arguments = array_slice($directive, 1);
				$method = pascalize($function) . 'Directive';

				if (!method_exists($this, $method))
					throw new \RuntimeException('Cannot parse file ' . $this->path . ', unknow directive ' . $function);

				$new_content .= call_user_func_array(array($this, $method), $arguments) . "\n";
			}
			else
				$new_content .= $line . "\n";
		}

		return $new_content;
	}

	static private function processFilters($path, $dir, $filters,  $vars)
	{
		$pipeline = Pipeline::getCurrentInstance();

		$content = file_get_contents($path);
		if (false === $content)
			throw new Exception\FileNotFound($path);

		foreach ($filters as $filter)
			$content = $pipeline->applyFilter($content, $filter, $path, $dir, $vars);

		return $content;
	}
}