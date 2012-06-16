<?php
require 'SassParser.php';

$sass = new SassParser(array());
echo $sass->toCss(file_get_contents('example.sass'), false); //false=  not a file. true = a file
echo "\n\n";
$scss = new SassParser(array('syntax' => SassFile::SCSS));
echo $sass->toCss(file_get_contents('example.scss'), false); //false=  not a file. true = a file