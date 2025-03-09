<?php

$basePath = realpath(__DIR__);
$pharFile = 'chat-app.phar';

if (file_exists($pharFile)) {
    unlink($pharFile);
}

$phar = new Phar($pharFile);
$phar->startBuffering();

$includePaths = [
    'src' => 'src/',
    'public' => 'public/',
    'config' => 'config/',
    'vendor' => 'vendor/',
    'schema' => 'schema.sql',
    '.env' => '.env',
];

foreach ($includePaths as $alias => $path) {
    $fullPath = realpath($basePath . DIRECTORY_SEPARATOR . $path);
    if ($fullPath === false || !file_exists($fullPath)) {
        echo "Skipped: $path (file or directory not found or inaccessible)\n";
        continue;
    }

    if (is_dir($fullPath)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $localPath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $item->getPathname());
                $phar->addFile($item->getPathname(), $localPath);
                echo "Added: $localPath\n";
            }
        }
    } elseif (is_file($fullPath)) {
        $localPath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $fullPath);
        $phar->addFile($fullPath, $localPath);
        echo "Added: $localPath\n";
    }
}

$stub = <<<EOT
<?php
Phar::mapPhar('chat-app.phar');
require 'phar://chat-app.phar/public/index.php';
__HALT_COMPILER();
EOT;
$phar->setStub($stub);

$phar->stopBuffering();

echo "Phar file '$pharFile' created successfully.\n";