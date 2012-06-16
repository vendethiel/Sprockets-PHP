<?php
namespace Filter;

abstract class Base implements iFilter
{
	public function getCachePath()
	{
		$directory = basename(__DIR__) . DIRECTORY_SEPARATOR . 'cache/';
		
		if (!file_exists($directory))
			mkdir($directory);
		
		return $directory;
	}
}