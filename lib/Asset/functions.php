<?php
namespace Asset;

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