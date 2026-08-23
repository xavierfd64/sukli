<?php
/**
 * Router script for PHP's built-in dev server (`php -S host:port router.php`).
 * Not used in production — Apache + .htaccess handles static files there.
 * Usage: php -S 127.0.0.1:8000 -t public public/router.php
 */
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if ($path !== '/' && file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    return false;
}
require __DIR__ . '/index.php';
