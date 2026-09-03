<?php

declare(strict_types=1);

namespace Sukli\Middleware;

use Sukli\Core\Auth;
use Sukli\Core\Request;
use Sukli\Core\View;

/**
 * Gates the entire /platform-admin area. Deliberately separate from
 * PermissionMiddleware (which checks module/action permissions within a
 * tenant) — is_platform_admin is a flag on the user, not scoped to any
 * organization's role/permission matrix, since a platform admin operates
 * across every tenant.
 */
class PlatformAdminMiddleware
{
    public static function handle(): callable
    {
        return function (Request $request): bool {
            if (!Auth::check()) {
                header('Location: ' . url('/login'));
                exit;
            }
            if (!Auth::isPlatformAdmin()) {
                http_response_code(403);
                View::render('errors/403', [], 'layouts/blank');
                exit;
            }
            return true;
        };
    }
}
