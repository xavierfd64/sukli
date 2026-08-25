<?php

declare(strict_types=1);

use Sukli\Core\Auth;
use Sukli\Core\Csrf;
use Sukli\Core\Icons;
use Sukli\Core\Request;
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

/** @return array{version:string,name:string} The full contents of config/version.php — the one place the app version is defined. */
function app_version_info(): array
{
    static $info = null;
    if ($info === null) {
        $info = require __DIR__ . '/../../config/version.php';
    }
    return $info;
}

function app_version(): string
{
    return (string) app_version_info()['version'];
}

/**
 * Builds an absolute URL for the given app-relative path. Scheme, host and
 * subfolder are computed from the current request (see
 * Request::basePath()) rather than trusting a value saved once at install
 * time — this is what makes uploaded-file URLs, asset links, and redirects
 * keep working correctly if the site is later moved to a different
 * domain/subfolder or switched from http to https, with nothing to
 * reconfigure. Falls back to the stored config value only when there is no
 * HTTP request to read from (CLI scripts).
 */
function url(string $path = '/'): string
{
    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . $_SERVER['HTTP_HOST'] . Request::basePath();
    } else {
        $base = rtrim((string) config_value('url', ''), '/');
    }

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

/** Best available display name for a supplier row: company name, else contact person, else a placeholder. */
function supplier_display_name(array $s): string
{
    $contact = trim(($s['contact_first_name'] ?? '') . ' ' . ($s['contact_last_name'] ?? ''));
    if (!empty($s['company_name']) && $contact) {
        return $s['company_name'] . ' (' . $contact . ')';
    }
    return $s['company_name'] ?: ($contact ?: 'Unnamed Supplier');
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
