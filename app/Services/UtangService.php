<?php

declare(strict_types=1);

namespace Sukli\Services;

use Sukli\Core\Auth;
use Sukli\Core\Database;

/**
 * Utang (store credit) ledger. A POS sale on Utang becomes an outstanding
 * balance on the customer's credit account; a payment reduces it. Balances
 * are tracked per-customer (not per individual utang_transactions row) —
 * simple and matches how a sari-sari store owner actually thinks about it.
 */
class UtangService
{
    /**
     * Extends Utang credit for any kind of transaction, not only a POS sale
     * — $saleId and $eloadTransactionId are each independently nullable so
     * an E-Load sale on Utang (which has no `sales` row of its own; see
     * eload_transactions) can link here through $eloadTransactionId while
     * leaving $saleId null, the same way a POS sale on Utang links through
     * $saleId while leaving $eloadTransactionId null.
     */
    public static function recordSaleCredit(int $storeId, int $customerId, ?int $saleId, float $amount, ?int $eloadTransactionId = null): void
    {
        Database::execute(
            "INSERT INTO customer_credit_accounts (store_id, customer_id, outstanding_balance)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE outstanding_balance = outstanding_balance + VALUES(outstanding_balance)",
            [$storeId, $customerId, $amount]
        );

        $balance = self::balance($customerId);

        Database::execute(
            "INSERT INTO utang_transactions (store_id, customer_id, sale_id, eload_transaction_id, amount, balance_after, status, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'outstanding', ?, NOW())",
            [$storeId, $customerId, $saleId, $eloadTransactionId, $amount, $balance, Auth::id()]
        );
    }

    public static function recordPayment(int $storeId, int $customerId, float $amount, string $method, ?string $note): void
    {
        $current = self::balance($customerId);
        $applied = min($amount, max(0, $current));

        Database::execute(
            "UPDATE customer_credit_accounts SET outstanding_balance = GREATEST(outstanding_balance - ?, 0), updated_at = NOW() WHERE customer_id = ?",
            [$applied, $customerId]
        );

        Database::execute(
            "INSERT INTO utang_payments (store_id, customer_id, amount, payment_method, note, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$storeId, $customerId, $amount, $method, $note, Auth::id()]
        );

        $newBalance = self::balance($customerId);
        $status = $newBalance <= 0 ? 'paid' : 'partially_paid';
        Database::execute(
            "UPDATE utang_transactions SET status = ? WHERE customer_id = ? AND status != 'paid'",
            [$status, $customerId]
        );
    }

    public static function balance(int $customerId): float
    {
        $row = Database::one("SELECT outstanding_balance FROM customer_credit_accounts WHERE customer_id = ?", [$customerId]);
        return $row ? (float) $row['outstanding_balance'] : 0.0;
    }
}
