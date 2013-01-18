<?php
namespace Asset\Filter;

class Css extends Base
{
	public function __invoke($content, $file, $dir, $vars)
	{
		return preg_replace_callback('`url\([\'"]?([a-zA-Z0-9/\._-]+)[\'"]?\)`', function ($match) use ($dir)
		{
			$file = new \Asset\File(($dir ? $dir . '/' : '') . $match[1]);
			return 'url(../' . $file->getFilepath() . ')'; //@todo don't simply guess ../ ><!
		}, $content);
	}
}