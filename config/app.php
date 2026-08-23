<?php

declare(strict_types=1);

use Sukli\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'Sukli'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => filter_var(Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim((string) Env::get('APP_URL', 'http://localhost'), '/'),
    'session_lifetime' => (int) Env::get('SESSION_LIFETIME', 120),
    'login_max_attempts' => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
    'login_lockout_minutes' => (int) Env::get('LOGIN_LOCKOUT_MINUTES', 15),
];
