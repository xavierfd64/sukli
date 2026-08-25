<?php

declare(strict_types=1);

use Sukli\Core\Env;
use Sukli\Core\Request;

// This 'url' value is only a fallback for contexts with no HTTP request to
// read from (CLI scripts like database/migrate.php) — every real web
// request instead has the url() helper (app/Core/helpers.php) compute
// scheme+host+subfolder live from that request, so it can't go stale if
// the install is later moved to a different domain or gets HTTPS. Still
// include the current request's scheme/host/subfolder here when one is
// available, so this fallback is accurate too, not just "http://localhost".
$fallbackUrl = 'http://localhost';
if (!empty($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $fallbackUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . Request::basePath();
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
