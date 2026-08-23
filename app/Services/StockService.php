<?php

declare(strict_types=1);

namespace Sukli\Services;

use Sukli\Core\Auth;
use Sukli\Core\Database;

/**
 * Single place that ever writes to products.current_stock — every stock
 * movement (initial stock, manual adjustment, a POS sale, a sale reversal)
 * goes through here so inventory_transactions stays a complete, trustworthy
 * ledger.
 */
class StockService
{
    private const INCREASING = ['initial', 'purchase_in', 'adjustment_in', 'void_reversal'];
    private const DECREASING = ['sale_out', 'adjustment_out'];

    public static function record(
        int $storeId,
        int $productId,
        string $type,
        int $quantity,
        ?string $referenceType = null,
        int|string|null $referenceId = null,
        ?string $note = null
    ): void {
        $quantity = abs($quantity);
        if ($quantity === 0) {
            return;
        }

        $signed = in_array($type, self::DECREASING, true) ? -$quantity : $quantity;

        Database::execute(
            "INSERT INTO inventory_transactions (store_id, product_id, type, quantity, reference_type, reference_id, note, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [$storeId, $productId, $type, $signed, $referenceType, $referenceId, $note, Auth::id()]
        );

        Database::execute(
            "UPDATE products SET current_stock = current_stock + ?, updated_at = NOW() WHERE id = ? AND store_id = ?",
            [$signed, $productId, $storeId]
        );
    }
}
