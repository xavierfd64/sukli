<?php

declare(strict_types=1);

// Front controller — every request funnels through here (see .htaccess).

error_reporting(E_ALL);

spl_autoload_register(function (string $class): void {
    $prefix = 'Sukli\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require __DIR__ . '/../app/Core/helpers.php';

use Sukli\Core\Env;
use Sukli\Core\Session;
use Sukli\Core\Request;
use Sukli\Core\Router;

Env::load(__DIR__ . '/../.env');

$appConfig = require __DIR__ . '/../config/app.php';
ini_set('display_errors', $appConfig['debug'] ? '1' : '0');
error_reporting($appConfig['debug'] ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);

set_exception_handler(function (Throwable $e) use ($appConfig): void {
    error_log('[Sukli] Uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if ($appConfig['debug']) {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo 'Something went wrong. Please try again.';
    }
});

Session::start();

$router = new Router();
(function (Router $router) {
    require __DIR__ . '/../routes/web.php';
})($router);

$request = new Request();
$router->dispatch($request);
