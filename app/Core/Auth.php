<?php

declare(strict_types=1);

namespace Sukli\Core;

/**
 * Session-based auth with rate-limited login attempts. Authorization itself
 * (permission checks) is enforced server-side in PermissionMiddleware /
 * controllers — never only by hiding menu items.
 */
class Auth
{
    private static ?array $userCache = null;

    public static function attempt(string $username, string $password, string $ip, string $userAgent): array
    {
        $config = require __DIR__ . '/../../config/app.php';
        $maxAttempts = $config['login_max_attempts'];
        $lockoutMinutes = $config['login_lockout_minutes'];

        $recentFailures = Database::one(
            "SELECT COUNT(*) AS cnt FROM login_attempts
             WHERE username = ? AND success = 0
               AND attempted_at > (NOW() - INTERVAL ? MINUTE)",
            [$username, $lockoutMinutes]
        );

        if ((int) ($recentFailures['cnt'] ?? 0) >= $maxAttempts) {
            return ['ok' => false, 'reason' => 'locked', 'message' => "Too many failed attempts. Try again in {$lockoutMinutes} minutes."];
        }

        $user = Database::one(
            "SELECT u.*, r.role_key, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.username = ? LIMIT 1",
            [$username]
        );

        $valid = $user && $user['status'] === 'active' && password_verify($password, $user['password_hash']);

        Database::execute(
            "INSERT INTO login_attempts (username, ip_address, user_agent, success, attempted_at) VALUES (?, ?, ?, ?, NOW())",
            [$username, $ip, $userAgent, $valid ? 1 : 0]
        );

        if (!$valid) {
            return ['ok' => false, 'reason' => 'invalid', 'message' => 'Invalid username or password.'];
        }

        Database::execute("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);

        self::establishSession($user);

        return ['ok' => true, 'user' => $user];
    }

    /**
     * Populates the session for an already-verified user — the part of
     * attempt() after password verification, factored out so registration
     * (which just created the account, nothing to verify) can log someone
     * in without faking a password check. $user must include role_key
     * (join users to roles, as attempt()'s query does).
     */
    public static function establishSession(array $user): void
    {
        Session::regenerate();
        Session::put('user_id', (int) $user['id']);
        Session::put('organization_id', (int) $user['organization_id']);
        Session::put('store_id', $user['store_id'] !== null ? (int) $user['store_id'] : null);
        Session::put('role_key', $user['role_key']);
        Session::put('role_id', (int) $user['role_id']);
        Session::put('is_platform_admin', (bool) $user['is_platform_admin']);
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function check(): bool
    {
        return Session::get('user_id') !== null;
    }

    public static function id(): ?int
    {
        return Session::get('user_id');
    }

    public static function storeId(): ?int
    {
        return Session::get('store_id');
    }

    public static function organizationId(): ?int
    {
        return Session::get('organization_id');
    }

    public static function role(): ?string
    {
        return Session::get('role_key');
    }

    public static function roleId(): ?int
    {
        return Session::get('role_id');
    }

    public static function isPlatformAdmin(): bool
    {
        return (bool) Session::get('is_platform_admin', false);
    }

    public static function hasRole(array $roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        if (self::$userCache !== null && (int) self::$userCache['id'] === self::id()) {
            return self::$userCache;
        }
        $user = Database::one(
            "SELECT u.*, r.role_key, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? LIMIT 1",
            [self::id()]
        );
        self::$userCache = $user ?: null;
        return self::$userCache;
    }
}
