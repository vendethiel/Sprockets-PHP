<?php
function vardump(){echo'<pre>';$e=func_get_args();foreach($e as $a)var_dump($a);}
function vdump(){call_user_func_array('vardump',func_get_args());exit;}
require '../vendor/autoload.php';


// read paths.json - see below
// you can of course pass a normal array !
$paths = str_replace('%template%', 'MyTemplate', file_get_contents('paths.json'));
$paths = json_decode($paths, true);

// create a pipeline
$pipeline = new Sprockets\Pipeline($paths);

$js = new Sprockets\Cache($pipeline, 'js');
$css = new Sprockets\Cache($pipeline, 'css');

function expect($type, $actual)
{
	$expect = file_get_contents('_expect/expect.' . $type);
	if ($expect === $actual)
		return print("$type : Test passed                                                      <br>\n");
	echo "ERROR : Expected <pre>$expect</pre>, got <pre>$actual</pre>";
}
expect('js', $js->getContent());
expect('css', $css->getContent());