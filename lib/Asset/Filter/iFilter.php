<?php
namespace Asset\Filter;

interface Interface
{
	/**
	 * @return string processed $content
	 */
	public function __invoke($content, $file, $dir, $vars);
}