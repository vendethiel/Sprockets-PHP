<?php
namespace Filter;

interface iFilter
{
	public function __invoke($content, $file, $vars);
}