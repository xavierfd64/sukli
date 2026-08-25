<?php

declare(strict_types=1);

namespace Sukli\Services;

use Sukli\Core\Database;

/**
 * Free-form store-scoped key/value settings (system_settings table) —
 * things that don't warrant their own column on `stores`, e.g. the
 * Auto Print Receipt toggle and the receipt customization flags.
 */
class SystemSettingsService
{
    public static function get(int $storeId, string $key, ?string $default = null): ?string
    {
        $row = Database::one(
            "SELECT setting_value FROM system_settings WHERE store_id = ? AND setting_key = ?",
            [$storeId, $key]
        );
        return $row ? $row['setting_value'] : $default;
    }

    public static function getBool(int $storeId, string $key, bool $default = false): bool
    {
        $value = self::get($storeId, $key);
        return $value === null ? $default : $value === '1';
    }

    public static function set(int $storeId, string $key, string $value): void
    {
        Database::execute(
            "INSERT INTO system_settings (store_id, setting_key, setting_value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
            [$storeId, $key, $value]
        );
    }
}
