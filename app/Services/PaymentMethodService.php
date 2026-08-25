<?php

declare(strict_types=1);

namespace Sukli\Services;

use Sukli\Core\Database;

/**
 * Which payment methods are available for this store — governs POS payment
 * buttons, split payment, and the Utang payment dialog alike. For 'gcash'
 * and 'utang' specifically, availability also requires the corresponding
 * Feature Management module to be enabled (FeatureService) — a store can
 * turn GCash recording off entirely, which must also remove it as a
 * checkout option, independent of this table's own toggle.
 */
class PaymentMethodService
{
    public const KEYS = ['cash', 'gcash', 'utang', 'ewallet', 'bank_transfer', 'other'];
    private const DEFAULT_LABELS = [
        'cash' => 'Cash', 'gcash' => 'GCash', 'utang' => 'Utang',
        'ewallet' => 'E-Wallet', 'bank_transfer' => 'Bank Transfer', 'other' => 'Other',
    ];

    private static array $cache = [];

    /** @return array<string, array{name:string, is_enabled:bool}> All 6 methods, keyed by method_key. */
    public static function all(int $storeId): array
    {
        if (isset(self::$cache[$storeId])) {
            return self::$cache[$storeId];
        }

        $rows = Database::all(
            "SELECT method_key, name, is_enabled FROM payment_methods WHERE store_id = ?",
            [$storeId]
        );

        $methods = [];
        foreach (self::KEYS as $key) {
            $methods[$key] = ['name' => self::DEFAULT_LABELS[$key], 'is_enabled' => in_array($key, ['cash', 'gcash', 'utang'], true)];
        }
        foreach ($rows as $row) {
            $methods[$row['method_key']] = ['name' => $row['name'], 'is_enabled' => (bool) $row['is_enabled']];
        }

        // gcash/utang additionally require their Feature Management module.
        $features = FeatureService::all($storeId);
        if (empty($features['gcash']['is_enabled'])) {
            $methods['gcash']['is_enabled'] = false;
        }
        if (empty($features['utang']['is_enabled'])) {
            $methods['utang']['is_enabled'] = false;
        }

        return self::$cache[$storeId] = $methods;
    }

    /** @return array<string, array{name:string, is_enabled:bool}> Only the enabled methods. */
    public static function enabled(int $storeId): array
    {
        return array_filter(self::all($storeId), fn ($m) => $m['is_enabled']);
    }

    public static function isEnabled(int $storeId, string $key): bool
    {
        return self::all($storeId)[$key]['is_enabled'] ?? false;
    }

    public static function update(int $storeId, string $key, bool $enabled, string $name): void
    {
        if (!in_array($key, self::KEYS, true)) {
            throw new \InvalidArgumentException('Unknown payment method.');
        }

        Database::execute(
            "INSERT INTO payment_methods (store_id, method_key, name, is_enabled) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), is_enabled = VALUES(is_enabled)",
            [$storeId, $key, $name, $enabled ? 1 : 0]
        );

        unset(self::$cache[$storeId]);
    }
}
