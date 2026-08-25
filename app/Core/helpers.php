<?php

declare(strict_types=1);

use Sukli\Core\Auth;
use Sukli\Core\Csrf;
use Sukli\Core\Icons;
use Sukli\Core\Session;
use Sukli\Core\View;
use Sukli\Services\PermissionService;

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function can(string $module, string $action): bool
{
    return PermissionService::roleHas(Auth::roleId(), $module, $action);
}

function config_value(string $key, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../../config/app.php';
    }
    return $config[$key] ?? $default;
}

function url(string $path = '/'): string
{
    $base = rtrim((string) config_value('url', ''), '/');
    if ($path === '' || $path === '/') {
        return $base . '/';
    }
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function old(string $key, string $default = ''): string
{
    $old = Session::flash('_old_input') ?? [];
    return e($old[$key] ?? $default);
}

function csrf_field(): string
{
    return Csrf::field();
}

function csrf_token(): string
{
    return Csrf::token();
}

function money(float|int|string $amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

function current_user(): ?array
{
    return Auth::check() ? Auth::user() : null;
}

function flash_get(string $key): ?string
{
    return Session::flash($key);
}

function active_class(string $current, string $target): string
{
    return $current === $target ? 'is-active' : '';
}

function icon(string $name, int $size = 18): string
{
    return Icons::svg($name, $size);
}

function view_partial(string $view, array $data = []): string
{
    return View::partial($view, $data);
}
