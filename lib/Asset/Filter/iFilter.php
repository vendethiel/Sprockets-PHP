<?php
namespace Asset\Filter;

interface Interface
{
	public function __invoke($content, $file, $dir, $vars);
}