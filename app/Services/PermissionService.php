<?php

declare(strict_types=1);

namespace Sukli\Services;

use Sukli\Core\Database;

/**
 * Granular role-based permission checks (module/action pairs) backing
 * PermissionMiddleware and the Roles & Permissions management screen.
 * Default role_permissions rows are seeded to exactly match this
 * build's earlier hardcoded role-only route protection, so wiring this
 * in changes nothing until an owner edits a role's permissions.
 */
class PermissionService
{
    private static array $cache = [];

    /** @return array Permissions grouped by module, e.g. ['pos' => [...rows]]. */
    public static function catalog(): array
    {
        $rows = Database::all("SELECT * FROM permissions ORDER BY module, sort_order");
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['module']][] = $row;
        }
        return $grouped;
    }

    /** @return array<int,bool> permission_id => allowed, for one role. */
    public static function forRole(int $roleId): array
    {
        $rows = Database::all("SELECT permission_id, allowed FROM role_permissions WHERE role_id = ?", [$roleId]);
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['permission_id']] = (bool) $row['allowed'];
        }
        return $map;
    }

    public static function roleHas(?int $roleId, string $module, string $action): bool
    {
        if (!$roleId) {
            return false;
        }
        if (!isset(self::$cache[$roleId])) {
            self::$cache[$roleId] = array_column(
                Database::all(
                    "SELECT CONCAT(p.module, '.', p.action) AS key_, rp.allowed
                     FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
                     WHERE rp.role_id = ?",
                    [$roleId]
                ),
                'allowed',
                'key_'
            );
        }
        return (bool) (self::$cache[$roleId]["{$module}.{$action}"] ?? false);
    }

    /** @param array<int,bool> $allowedByPermissionId */
    public static function setRolePermissions(int $roleId, array $allowedByPermissionId): void
    {
        foreach ($allowedByPermissionId as $permissionId => $allowed) {
            Database::execute(
                "INSERT INTO role_permissions (role_id, permission_id, allowed) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)",
                [$roleId, (int) $permissionId, $allowed ? 1 : 0]
            );
        }
        unset(self::$cache[$roleId]);
    }
}
