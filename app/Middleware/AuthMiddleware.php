<?php

declare(strict_types=1);

namespace Sukli\Middleware;

use Sukli\Core\Auth;
use Sukli\Core\Request;

class AuthMiddleware
{
    public static function handle(): callable
    {
        return function (Request $request): bool {
            if (!Auth::check()) {
                header('Location: ' . url('/login'));
                exit;
            }
            return true;
        };
    }
}
