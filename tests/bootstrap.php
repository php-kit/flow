<?php
require __DIR__ . '/../src/globals.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'PhpKit\\Flow\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/../src/Flow/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
