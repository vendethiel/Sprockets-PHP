<?php
namespace Filter;

abstract class Base implements iFilter
{
	public function getCacheDir($file = '', $class = '')
	{
		//vendor/__cache/
		$directory = dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR . '__cache/';
		
		if (!file_exists($directory))
			mkdir($directory);
		
		return $directory . ($file == '' ? '' : str_replace('\\', '__', $class) . '_' . str_replace('.', '_', basename($file)));
	}
}