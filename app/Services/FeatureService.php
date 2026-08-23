<?php

declare(strict_types=1);

namespace Sukli\Services;

use Sukli\Core\Database;

/**
 * Reads/writes Feature Management flags (E-Load, GCash, Utang). Disabling a
 * feature must hide/deactivate it everywhere without deleting historical
 * records — every consumer of this service should only ever hide UI/routes,
 * never delete rows.
 */
class FeatureService
{
    private static array $cache = [];

    public const KEYS = ['eload', 'gcash', 'utang'];

    public static function all(int $storeId): array
    {
        if (isset(self::$cache[$storeId])) {
            return self::$cache[$storeId];
        }

        $rows = Database::all(
            "SELECT feature_key, is_enabled, show_in_nav, show_in_dashboard FROM feature_settings WHERE store_id = ?",
            [$storeId]
        );

        $features = [];
        foreach (self::KEYS as $key) {
            $features[$key] = ['is_enabled' => true, 'show_in_nav' => true, 'show_in_dashboard' => true];
        }
        foreach ($rows as $row) {
            $features[$row['feature_key']] = [
                'is_enabled' => (bool) $row['is_enabled'],
                'show_in_nav' => (bool) $row['show_in_nav'],
                'show_in_dashboard' => (bool) $row['show_in_dashboard'],
            ];
        }

        return self::$cache[$storeId] = $features;
    }

    public static function isEnabled(int $storeId, string $key): bool
    {
        return self::all($storeId)[$key]['is_enabled'] ?? false;
    }

    public static function showInNav(int $storeId, string $key): bool
    {
        $f = self::all($storeId)[$key] ?? null;
        return $f !== null && $f['is_enabled'] && $f['show_in_nav'];
    }

    public static function showInDashboard(int $storeId, string $key): bool
    {
        $f = self::all($storeId)[$key] ?? null;
        return $f !== null && $f['is_enabled'] && $f['show_in_dashboard'];
    }

    public static function update(int $storeId, string $key, bool $enabled, bool $showInNav, bool $showInDashboard): void
    {
        if (!in_array($key, self::KEYS, true)) {
            throw new \InvalidArgumentException('Unknown feature key.');
        }

        Database::execute(
            "INSERT INTO feature_settings (store_id, feature_key, is_enabled, show_in_nav, show_in_dashboard)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), show_in_nav = VALUES(show_in_nav), show_in_dashboard = VALUES(show_in_dashboard)",
            [$storeId, $key, $enabled ? 1 : 0, $showInNav ? 1 : 0, $showInDashboard ? 1 : 0]
        );

        unset(self::$cache[$storeId]);
    }
}
