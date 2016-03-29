<?php

function getFilesList($arguments, $options)
{
    $from = count($arguments) - 1;
    $files = [];

    for ($i = $from; $i > 0; $i--) {
        $arg = $arguments[$i];
        $trimmed = ltrim($arg, '-');
        $isShort = strpos($arg, '--') === 0 ? false : true;

        if (false === strpos($arg, '-')) {
            $files[] = $arg;
        } elseif ($isShort && strlen($arg) > 2) {
            break;
        } elseif (!$isShort && preg_match('/[a-z]+=.{1,}/', $trimmed)) {
            break;
        } else {
            array_pop($files);
            break;
        }
    }

    return $files;
}
