<?php

declare(strict_types=1);

namespace Sukli\Middleware;

use Sukli\Core\Auth;
use Sukli\Core\Request;
use Sukli\Services\SubscriptionService;

class AuthMiddleware
{
    /** Requires login AND a usable subscription — the gate almost every app route uses. Platform Admins are never blocked by a tenant's subscription state. */
    public static function handle(): callable
    {
        return function (Request $request): bool {
            self::requireLogin();
            if (!Auth::isPlatformAdmin() && !SubscriptionService::isUsable((int) Auth::organizationId())) {
                header('Location: ' . url('/subscription'));
                exit;
            }
            return true;
        };
    }

    /**
     * Requires login only, no subscription check — for the handful of
     * routes that must stay reachable even once an organization's
     * subscription has expired: logout, and the expired/renewal pages
     * themselves. Without this, an expired organization could never reach
     * the very page that lets them pay to fix that.
     */
    public static function authOnly(): callable
    {
        return function (Request $request): bool {
            self::requireLogin();
            return true;
        };
    }

    private static function requireLogin(): void
    {
        if (!Auth::check()) {
            header('Location: ' . url('/login'));
            exit;
        }
    }
}
