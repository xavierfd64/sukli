<?php

declare(strict_types=1);

namespace Sukli\Services;

use Sukli\Core\Database;

/**
 * Which branches (stores) a user is allowed to switch into. An
 * Organization Owner implicitly sees every active branch of their own
 * organization (an owner runs the whole business, not one branch) —
 * everyone else gets exactly the rows granted to them in
 * user_store_access, nothing more. Branch identity is never trusted from
 * the request; every check here re-derives access from these tables.
 */
class BranchAccessService
{
    /** @return array All branches this user may switch into, Main Branch first. */
    public static function accessibleStores(int $userId, int $organizationId, bool $isOwner): array
    {
        if ($isOwner) {
            return Database::all(
                "SELECT * FROM stores WHERE organization_id = ? AND status = 'active' ORDER BY is_main_branch DESC, name",
                [$organizationId]
            );
        }
        return Database::all(
            "SELECT s.* FROM stores s
             JOIN user_store_access usa ON usa.store_id = s.id
             WHERE usa.user_id = ? AND s.organization_id = ? AND s.status = 'active'
             ORDER BY s.is_main_branch DESC, s.name",
            [$userId, $organizationId]
        );
    }

    /** True if $userId is allowed to switch into $storeId — the authoritative check the switch endpoint relies on. */
    public static function canAccess(int $userId, int $organizationId, int $storeId, bool $isOwner): bool
    {
        if ($isOwner) {
            return (bool) Database::one(
                "SELECT id FROM stores WHERE id = ? AND organization_id = ? AND status = 'active'",
                [$storeId, $organizationId]
            );
        }
        return (bool) Database::one(
            "SELECT usa.id FROM user_store_access usa JOIN stores s ON s.id = usa.store_id
             WHERE usa.user_id = ? AND usa.store_id = ? AND s.organization_id = ? AND s.status = 'active'",
            [$userId, $storeId, $organizationId]
        );
    }

    /** Grants a user access to a branch (idempotent). */
    public static function grant(int $userId, int $storeId): void
    {
        Database::execute(
            "INSERT INTO user_store_access (user_id, store_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE store_id = VALUES(store_id)",
            [$userId, $storeId]
        );
    }

    /** Revokes a user's access to a branch. Never revokes their home branch (users.store_id) — see UserController. */
    public static function revoke(int $userId, int $storeId): void
    {
        Database::execute("DELETE FROM user_store_access WHERE user_id = ? AND store_id = ?", [$userId, $storeId]);
    }
}
