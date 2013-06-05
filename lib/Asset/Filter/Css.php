<?php
namespace Asset\Filter;

use Asset\Pipeline;

class Css extends Base
{
	public function __invoke($content, $file, $dir, $vars)
	{
		// XXX change that
		//$base_url = str_repeat('../', substr_count(Pipeline::getCache, needle));

		return preg_replace_callback('`url\([\'"]?([a-zA-Z0-9/\._-]+)[\'"]?\)`', function ($match) use ($dir)
		{
			$file = new \Asset\File(($dir ? $dir . '/' : '') . $match[1]);
			return 'url(../' . $file->getFilepath() . ')'; //@todo don't simply guess ../ ><!
		}, $content);
	}
}