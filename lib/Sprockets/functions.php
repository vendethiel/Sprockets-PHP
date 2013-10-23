<?php
namespace Sprockets;

if (!function_exists('ensure_directory'))
{
	/**
	 * ensures a directory exist, by creating it if it does no & it's possible to
	 *
	 * @param string $dir directory
	 */
	function ensure_directory($dir)
	{
		if (file_exists($dir))
		{
			if (!is_dir($dir))
				throw new InvalidArgumentException($dir);
		}
		else
		{
			$dir = explode('/', str_replace(DIRECTORY_SEPARATOR, '/', $dir));
			$cDir = ''; //complete dir

			foreach ($dir as $d)
			{
				$cDir .= $d . '/';

				if (!file_exists($cDir) && !@mkdir($cDir))
					throw RuntimeException("can't create $cDir");
			}
		}
	}
}

if (!function_exists('vdump'))
{
	function vdump()
	{
		echo '<pre>';
		
		$a = func_get_args();
		foreach ($a as $v)
			var_dump($v);

		exit;
	}
}

if (!function_exists('camelize'))
{
	function camelize($s)
	{
		$s=str_replace('_',' ',$s);
		$s=ucwords($s);
		return str_replace(' ','',$s);
	}
}
if (!function_exists('pascalize'))
{
	function pascalize($s)
	{
		return lcfirst(camelize($s));
	}
}