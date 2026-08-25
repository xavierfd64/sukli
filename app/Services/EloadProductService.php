<?php

declare(strict_types=1);

namespace Sukli\Services;

use Sukli\Core\Database;

/**
 * Admin-manageable catalog of E-Load products (e.g. "Globe GoSAKTO 50"),
 * each tied to a network from NetworkService. Earnings is always derived as
 * selling_price - cost, never assumed — additional_charge only feeds a
 * *suggested* selling price in the UI; the admin can override it freely.
 */
class EloadProductService
{
    /** @return array All products for the store (active and inactive), for the management screen. */
    public static function all(int $storeId): array
    {
        return Database::all(
            "SELECT * FROM eload_products WHERE store_id = ? ORDER BY network, name",
            [$storeId]
        );
    }

    /** @return array Active products only, for the E-Load recording form. */
    public static function active(int $storeId): array
    {
        return Database::all(
            "SELECT * FROM eload_products WHERE store_id = ? AND is_active = 1 ORDER BY network, name",
            [$storeId]
        );
    }

    public static function find(int $storeId, int $id): ?array
    {
        return Database::one("SELECT * FROM eload_products WHERE id = ? AND store_id = ?", [$id, $storeId]);
    }

    public static function create(int $storeId, string $network, string $name, float $loadValue, float $cost, float $additionalCharge, float $sellingPrice): void
    {
        Database::execute(
            "INSERT INTO eload_products (store_id, network, name, load_value, cost, additional_charge, selling_price, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
            [$storeId, $network, $name, $loadValue, $cost, $additionalCharge, $sellingPrice]
        );
    }

    public static function update(int $storeId, int $id, string $network, string $name, float $loadValue, float $cost, float $additionalCharge, float $sellingPrice): void
    {
        Database::execute(
            "UPDATE eload_products SET network = ?, name = ?, load_value = ?, cost = ?, additional_charge = ?, selling_price = ?
             WHERE id = ? AND store_id = ?",
            [$network, $name, $loadValue, $cost, $additionalCharge, $sellingPrice, $id, $storeId]
        );
    }

    public static function toggle(int $storeId, int $id): void
    {
        Database::execute(
            "UPDATE eload_products SET is_active = NOT is_active WHERE id = ? AND store_id = ?",
            [$id, $storeId]
        );
    }
}
