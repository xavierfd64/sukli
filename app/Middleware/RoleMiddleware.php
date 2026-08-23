<?php

declare(strict_types=1);

namespace Sukli\Middleware;

use Sukli\Core\Auth;
use Sukli\Core\Request;
use Sukli\Core\View;

class RoleMiddleware
{
    public static function only(array $roles): callable
    {
        return function (Request $request) use ($roles): bool {
            if (!Auth::hasRole($roles)) {
                http_response_code(403);
                View::render('errors/403', [], 'layouts/blank');
                exit;
            }
            return true;
        };
    }
}
