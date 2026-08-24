<?php

declare(strict_types=1);

use Sukli\Core\Env;

// Before install (i.e. before config/installed.php exists), fall back to
// the current request's own scheme+host instead of a hardcoded "localhost"
// so installer-generated links/assets work on whatever domain the site is
// actually reached at. Once installed, the saved APP_URL always wins.
$fallbackUrl = 'http://localhost';
if (!empty($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $fallbackUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
}

// Installer-written config (config/installed.php) always wins once it
// exists; .env / real environment variables remain a supported override
// for local development or advanced/CLI use.
$installedApp = Env::installed()['app'] ?? [];

return [
    'name' => $installedApp['name'] ?? Env::get('APP_NAME', 'Sukli'),
    'env' => $installedApp['env'] ?? Env::get('APP_ENV', 'production'),
    'debug' => filter_var($installedApp['debug'] ?? Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim((string) ($installedApp['url'] ?? Env::get('APP_URL', $fallbackUrl)), '/'),
    'session_lifetime' => (int) Env::get('SESSION_LIFETIME', 120),
    'login_max_attempts' => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
    'login_lockout_minutes' => (int) Env::get('LOGIN_LOCKOUT_MINUTES', 15),
];
