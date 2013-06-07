<?php
namespace Sprockets\Exception;

class FileNotFound extends AssetNotFound
{
	public function __construct($name, $type)
	{
		parent::__construct("The asset file '$name' of type '$type' wasn't found.\n" . parent::getTraceAsString());
	}
}