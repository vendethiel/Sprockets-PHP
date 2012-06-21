<?php
namespace Filter;
use Asset\Pipeline;

abstract class Base implements iFilter
{
	public function getCacheDir($file = '', $class = '')
	{
		$class = str_replace('\\', '__', $class);
		$file = $file == '' ? '' : $class . '_' . str_replace('.', '_', basename($file));

		return Pipeline::getCacheDirectory() . $file;
	}
}