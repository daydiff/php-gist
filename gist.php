<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/Gist.php';
require __DIR__ . '/src/functions.php';

$gist = new Daydiff\Gist\Gist();

$shortDescr = 'f:pd:u:l::r:a';
$longDescr = ['login', 'filename:', 'private', 'description:', 'update:', 'list', 'read:'];

$opts = getopt($shortDescr, $longDescr);
$files = getFilesList($argv, $shortDescr, $longDescr);
$options = [];

if (isset($opts['login'])) {
    $gist->login();
    exit;
}

if (isset($opts['l']) || isset($opts['list'])) {
    $user = isset($opts['l']) ? $opts['l'] : $opts['list'];
    $gist->all($user);
    exit;
}

if (isset($opts['r']) || isset($opts['read'])) {
    $id = isset($opts['r']) ? $opts['r'] : $opts['read'];
    $file_name = count($files) ? reset($files) : false;
    $gist->read($id, $file_name);
    exit;
}

if (isset($opts['d']) || isset($opts['description'])) {
    $options['description'] = isset($opts['d']) ? $opts['d'] : $opts['description'];
}

if (isset($opts['p']) || isset($opts['private'])) {
    $options['public'] = isset($opts['p']) ? !!$opts['p'] : !!$opts['private'];
}

if (isset($opts['f']) || isset($opts['filename'])) {
    $options['filename'] = isset($opts['f']) ? $opts['f'] : $opts['filename'];
}

if ($files) {
    $data = [];

    foreach ($files as $file) {
        if (!file_exists($file) || !is_readable($file)) {
            print "Can't read file " . escapeshellarg($file) . "\n";
            exit;
        }
        $data[$file] = file_get_contents($file);
    }
    $gist->createMulti($data, $options);
}

echo "...\n";