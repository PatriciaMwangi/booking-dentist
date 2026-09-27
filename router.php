<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$file = __DIR__ . $path;

// Static assets can be served directly.
$staticExtensions = [
    'css',
    'js',
    'png',
    'jpg',
    'jpeg',
    'gif',
    'svg',
    'webp',
    'ico',
    'woff',
    'woff2',
    'ttf',
    'map'
];

$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

if (
    $extension !== '' &&
    in_array($extension, $staticExtensions, true) &&
    is_file($file)
) {
    return false;
}

// Everything else goes through index.php.
require __DIR__ . '/index.php';