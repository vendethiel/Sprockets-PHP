<?php
function vardump(){echo'<pre>';$e=func_get_args();foreach($e as $a)var_dump($a);}
function vdump(){call_user_func_array('vardump',func_get_args());exit;}
function camelize($s){$s=str_replace('_',' ',$s);$s=ucwords($s);return str_replace(' ','',$s);}
function pascalize($s){return lcfirst(camelize($s));}
function __autoload($s) { return require 'lib/' . str_replace(array('_', '\\'), '/', $s) . '.php'; }


$pipeline = new Asset\Pipeline(glob('assets/*'));
vdump($pipeline('css'));
$cache = new Asset\Cache($pipeline, 'css');
vdump($cache->getContent());