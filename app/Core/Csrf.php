<?php

declare(strict_types=1);

namespace Sukli\Core;

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verify(?string $token): bool
    {
        $expected = $_SESSION['_csrf_token'] ?? null;
        return is_string($expected) && is_string($token) && hash_equals($expected, $token);
    }
}
