<?php
namespace Sprockets;

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
		$this->pipeline = Pipeline::getCurrentInstance();
		$this->locator = $this->pipeline->getLocator();

		$this->path = trim(trim($path, '/'), '\\');
		$this->directory = '.' === ($dirname = dirname($path)) ? '' : $dirname;

		$this->vars = $vars;

		$this->file = basename($path);

		list($this->name, $this->type, $i) = $this->locator->getNameAndExtension($this->file);

		$this->path_with_simple_filename = ('' === $this->directory ? '' : $this->directory . '/') . $this->name;
		$this->filepath = $this->locator->getFile($this->path_with_simple_filename, $this->type);

		if (!$this->type)
			vdump($this->type, $path, 'no type');

		$full_filename = explode('/', $this->filepath);
		$this->full_filename = end($full_filename);
		$full_filename_parts = explode('.', $this->full_filename);

		$this->filters = array_reverse(array_slice($full_filename_parts, $i + 1)); //['less', 'php'] => ['php', 'less']

		if (in_array($this->type, array('html', 'css', 'js')))
			$this->pipeline->addDependency($this->type, $this->filepath);
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
		if (class_exists('Sprockets\Filter\\' . ucfirst($this->type)))
			$filters[] = $this->type;

		$content = self::processFilters($this->filepath, $this->directory, $filters, $this->vars);

		if (!$this->isAsset())
			return $content; //no directives

		if (!$content)
			vdump($this->filepath);
		return $this->processDirectives($content);
	}

	public function process()
	{
		if ($this->isAsset() && $this->locator->hasProcessedFile($this->filepath))
			return ' '; //hasProcessedFile will add it otherwise

		return $this->getProcessedContent();
	}

	public function isAsset()
	{
		return $this->type == 'js' || $this->type == 'css';
	}

	public function __toString()
	{
		try {
			$e = $this->process();
			if (empty($e) || !is_string($e))
			{
				debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
				vdump($e, $this->filters, $this->getFilepath(), file_get_contents($this->getFilepath()));
			}
			return $e;
		} catch (Exception\Asset $e) {
			exit('Asset exception (' . $this->getFilepath() . ') : ' . $e->getMessage());
		} catch (\Exception $e) {
			exit('External exception (' . $this->getFilepath() . ') : ' . $e->getMessage() .
			 '<pre>' . $e->getTraceAsString() . '</pre>');
		}
	}


	private function processDirectives($content)
	{
		/**
		 * Recognizes :
		 * ALl:
		 *  NEWLINE" *=" (processed only in comment)
		 * JS:
		 *  "//="
		 *  "#="
		 */
		if (false === strpos($content, "\n *=") //regexp ?
		 && false === strpos($content, '//=')
		 && false === strpos($content, '#='))
			return $content;

		$new_content = '';

		$lines = explode("\n", $content);

		$in_comment = false;
		for ($i = 0, $len = count($lines); $i < $len; ++$i)
		{
			$line = $lines[$i];

			if (substr($line, 0, 2) == '/*')
			{
				$in_comment = true;
				continue;
			}
			if ($in_comment && substr(trim($line), 0, 2) == '*/')
			{
				$in_comment = false;
				continue;
			}

			//a bit verbose, but definitely more readable ...
			$is_directive = false;
			if ($in_comment && substr(trim($line), 0, 2) == '*=')
				$is_directive = true;
			if ($this->type == 'js')
			{
				if (substr($line, 0, 3) == '//=')
					$is_directive = true;
				if (substr($line, 0, 2) == '#=')
					$is_directive = true;
			}

			if ($is_directive)
			{
				$directive = explode(' ', trim(substr($line, 3)));

				$function = $directive[0];
				$argument = $directive[1];
				$last = substr($argument, -1);

				while ($last == '\\' || $last == ',')
				{ //parse as many lines as needed
					if ($last == '\\') //  remove trailing \    remove leading //
						$argument = substr($argument, 0, -1) . trim(substr($next = trim($lines[++$i]), 2));
					else if ($last == ',')
					{
						$next = trim($lines[++$i]);
						$argument .= ltrim($next, '/#*=');
					}

					$last = substr($next, -1);
				}

				$method = pascalize($function) . 'Directive';

				if (!method_exists($this, $method))
					throw new \RuntimeException('Cannot parse file ' . $this->path . ', unknow directive ' . $function);

				$new_content .= call_user_func(array($this, $method), $argument) . "\n";
			}
			else if (!$in_comment) //we can't balance comments.
				$new_content .= $line . "\n";
		}

		return $new_content;
	}

	private function requireDirective($name)
	{
		if (false !== $start = strpos($name, '{'))
		{
			$end = strpos($name, '}');
			$elements = explode(',', substr($name, $start+1, $end-$start-1));
			$name = substr_replace($name, '%s', $start, $end-$start+1);

			$code = '';
			foreach ($elements as $element)
				$code .= (string) $this->requireDirective(sprintf($name, trim($element)));

			return $code;
		}

		$basename = $name[0] == '/'
		// handle absolute paths
		 ? substr($name, 1)
		// remove "./" prefix from path
		 : $this->directory . str_replace('./', '/', $name);

		if ($this->locator->hasFile($file = $basename, $this->type))
			return (string) new File($file . '.' . $this->type, $this->vars);
		else if ($this->locator->hasFile($index_file = $basename . '/index', $this->type))
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

	private function skipDirective($name = '')
	{
		$this->locator->skipFile($name);
	}

	private function dependsOnDirective($name)
	{ //allows to depend on a file, even if this one isn't included
		if (strpos($name, '.') === false)
			$type = $this->type;
		else
		{ //depending on another asset type. In example, //= depends_on image.png
			$name_parts = explode('.', $name);
			$type = $name_parts[1]; //"style" "css" *filters
		}

		$this->pipeline->addDependency($this->type, $this->locator->getFile($name, $type));
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