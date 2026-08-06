<?php

$sourceDirectory = dirname(__DIR__) . '/src';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDirectory));
$failed = false;

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $output = array();
    $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($file->getPathname()), $output, $code);
    if ($code !== 0) {
        $failed = true;
        fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
    }
}

exit($failed ? 1 : 0);
