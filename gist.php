<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/Gist.php';

$gist = new Daydiff\Gist\Gist();

var_dump($argv);

$args = getopt('f:pd:u:l::r:a', ['login']);
var_dump($args);

//$gist->login();