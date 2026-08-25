<?php

declare(strict_types=1);

namespace Sukli\Services;

use Sukli\Core\Database;

/**
 * Admin-manageable list of E-Load networks (Globe, Smart, TNT, DITO, ...)
 * backing the E-Load form's Network dropdown — replaces a hardcoded list
 * so a store can add, rename, or retire networks on its own.
 */
class NetworkService
{
    /** @return array All networks for the store, including disabled ones, for the management screen. */
    public static function all(int $storeId): array
    {
        return Database::all(
            "SELECT * FROM networks WHERE store_id = ? ORDER BY sort_order, name",
            [$storeId]
        );
    }

    /** @return string[] Enabled network names only, for the E-Load form dropdown. */
    public static function enabled(int $storeId): array
    {
        return array_column(Database::all(
            "SELECT name FROM networks WHERE store_id = ? AND is_enabled = 1 ORDER BY sort_order, name",
            [$storeId]
        ), 'name');
    }

    public static function create(int $storeId, string $name): void
    {
        Database::execute(
            "INSERT INTO networks (store_id, name, is_enabled, sort_order) VALUES (?, ?, 1, ?)",
            [$storeId, $name, (int) (Database::one("SELECT COUNT(*) AS c FROM networks WHERE store_id = ?", [$storeId])['c'] ?? 0)]
        );
    }

    public static function toggle(int $storeId, int $id): void
    {
        Database::execute(
            "UPDATE networks SET is_enabled = NOT is_enabled WHERE id = ? AND store_id = ?",
            [$id, $storeId]
        );
    }
}
