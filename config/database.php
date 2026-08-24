<?php

declare(strict_types=1);

use Sukli\Core\Env;

// Installer-written config (config/installed.php) always wins once it
// exists; .env / real environment variables remain a supported override
// for local development or advanced/CLI use (see database/migrate.php).
$installedDb = Env::installed()['db'] ?? [];

return [
    'host' => $installedDb['host'] ?? Env::get('DB_HOST', '127.0.0.1'),
    'port' => $installedDb['port'] ?? Env::get('DB_PORT', '3306'),
    'database' => $installedDb['database'] ?? Env::get('DB_DATABASE', 'sukli'),
    'username' => $installedDb['username'] ?? Env::get('DB_USERNAME', 'root'),
    'password' => $installedDb['password'] ?? Env::get('DB_PASSWORD', ''),
];
