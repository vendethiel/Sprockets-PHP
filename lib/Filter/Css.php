<?php
namespace Filter;

class Css implements iFilter
{
	public function __invoke($content, $file, $args)
	{
		return preg_replace_callback('`url\([\'"]?([a-zA-Z0-9/\._-]+)[\'"]?\)`', function ($match)
		{
			$file = new \Asset\File($match[1]);
			return 'url(../' . $file->getFilepath() . ');';
		}, $content);
	}
}