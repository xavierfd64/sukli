<?php

declare(strict_types=1);

namespace Sukli\Middleware;

use Sukli\Core\Auth;
use Sukli\Core\Request;
use Sukli\Core\View;
use Sukli\Services\PermissionService;

class PermissionMiddleware
{
    public static function requires(string $module, string $action): callable
    {
        return function (Request $request) use ($module, $action): bool {
            if (!PermissionService::roleHas(Auth::roleId(), $module, $action)) {
                http_response_code(403);
                View::render('errors/403', [], 'layouts/blank');
                exit;
            }
            return true;
        };
    }
}
