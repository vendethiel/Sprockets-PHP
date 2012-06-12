<?php
namespace Asset;

abstract class Base
{
	private $name, $type;

	public function __construct($name, $type)
	{
		$this->name = $name;
		$this->type = $type;
		
		$this->create();
	}
	
	protected function create() { }
	abstract public function process();
	public function __toString()
	{
		return $this->process();
	}
}