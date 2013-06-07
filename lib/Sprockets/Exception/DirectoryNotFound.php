<?php
namespace Sprockets\Exception;

class DirectoryNotFound extends AssetNotFound
{
	public function __construct($name)
	{
		parent::__construct("The asset directory '$name' wasn't found.\n" . parent::getTraceAsString());
	}
}