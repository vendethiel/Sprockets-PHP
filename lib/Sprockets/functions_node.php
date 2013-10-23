<?php
define('NODE_MODULES_PATH', __DIR__ . '/../../node_modules/');

function process_node($cmd)
{
    $log = $this->getCacheDir('node_log');
    @unlink($log);

    $script = "node " . NODE_MODULES_PATH . $cmd;
    exec("$script > $log 2>&1", $out); //2>&1 redirects stderr to stdout

    return file_get_contents($log);
}