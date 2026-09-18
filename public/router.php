<?php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__.$path;

// Let PHP's built-in server serve compiled AssetMapper files directly.
if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__.'/index.php';
