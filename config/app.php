<?php

declare(strict_types=1);

use Sukli\Core\Env;

// Before .env exists (i.e. during first-run installation), fall back to the
// current request's own scheme+host instead of a hardcoded "localhost" so
// installer-generated links/assets work on whatever domain the site is
// actually reached at. Once APP_URL is set (post-install), it always wins.
$fallbackUrl = 'http://localhost';
if (!empty($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $fallbackUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
}

return [
    'name' => Env::get('APP_NAME', 'Sukli'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => filter_var(Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim((string) Env::get('APP_URL', $fallbackUrl), '/'),
    'session_lifetime' => (int) Env::get('SESSION_LIFETIME', 120),
    'login_max_attempts' => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
    'login_lockout_minutes' => (int) Env::get('LOGIN_LOCKOUT_MINUTES', 15),
];
