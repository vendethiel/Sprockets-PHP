<?php
function vardump(){echo'<pre>';$e=func_get_args();foreach($e as $a)var_dump($a);}
function vdump(){call_user_func_array('vardump',func_get_args());exit;}
function __autoload($s) {
	$path = '../lib/' . str_replace(array('_', '\\'), '/', $s) . '.php';
	if (file_exists($path)) return require $path; else return false; }


// read paths.json - see below
// you can of course pass a normal array !
$paths = str_replace('%template%', 'MyTemplate', file_get_contents('paths.json'));
$paths = json_decode($paths, true);

// create a pipeline
$pipeline = new Sprockets\Pipeline($paths);

$js = new Sprockets\Cache($pipeline, 'js');
$css = new Sprockets\Cache($pipeline, 'css');

echo (string) $js, "\n", $css->getContent();