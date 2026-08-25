<?php

declare(strict_types=1);

namespace Sukli\Services;

use Sukli\Core\Database;

/**
 * Admin-manageable GCash service-charge brackets — e.g. 1-500 => 10,
 * 501-1000 => 20 — used to auto-calculate the suggested service charge
 * on the GCash Cash-In/Cash-Out form as the amount is typed.
 */
class GcashChargeBracketService
{
    /** @return array All brackets for the store, ordered low to high. */
    public static function all(int $storeId): array
    {
        return Database::all(
            "SELECT * FROM gcash_charge_brackets WHERE store_id = ? ORDER BY sort_order, min_amount",
            [$storeId]
        );
    }

    public static function chargeFor(int $storeId, float $amount): ?float
    {
        foreach (self::all($storeId) as $b) {
            $min = (float) $b['min_amount'];
            $max = $b['max_amount'] !== null ? (float) $b['max_amount'] : null;
            if ($amount >= $min && ($max === null || $amount <= $max)) {
                return (float) $b['charge'];
            }
        }
        return null;
    }

    public static function create(int $storeId, float $min, ?float $max, float $charge): void
    {
        $sortOrder = (int) (Database::one("SELECT COUNT(*) AS c FROM gcash_charge_brackets WHERE store_id = ?", [$storeId])['c'] ?? 0);
        Database::execute(
            "INSERT INTO gcash_charge_brackets (store_id, min_amount, max_amount, charge, sort_order) VALUES (?, ?, ?, ?, ?)",
            [$storeId, $min, $max, $charge, $sortOrder]
        );
    }

    public static function delete(int $storeId, int $id): void
    {
        Database::execute("DELETE FROM gcash_charge_brackets WHERE id = ? AND store_id = ?", [$id, $storeId]);
    }
}
