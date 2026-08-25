<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Services\PaymentMethodService;

/**
 * Income is a summary-only view — it auto-aggregates from POS sales,
 * E-Load profit, and GCash service charges rather than accepting manual
 * entries. Historical rows in income_records (from before this change)
 * are still shown, read-only, so nothing entered previously is lost.
 *
 * POS revenue is broken out per payment method from the `payments` table
 * (one row per method per sale, including each leg of a split payment)
 * rather than summed from sales.total, so a sale that's part cash / part
 * Utang lands its cash portion under Cash Sales and its credit portion
 * under Utang Sales — never both, and never the full amount twice.
 *
 * A Utang sale counts as income once, at the moment it's made (the store
 * gave up real goods for a promise to pay) — the same way accrual
 * bookkeeping treats a credit sale. Utang Payments Collected is shown
 * separately for cash-flow visibility only and is deliberately excluded
 * from Total Income, since including it would count that same sale a
 * second time once the customer pays it off.
 */
class IncomeController extends Controller
{
    private const METHOD_LABELS = [
        'cash' => 'POS Cash Sales',
        'gcash' => 'POS GCash Sales',
        'ewallet' => 'POS E-Wallet Sales',
        'bank_transfer' => 'POS Bank Transfer Sales',
        'other' => 'POS Other Sales',
        'utang' => 'Utang Sales (Store Credit)',
    ];

    public function index(Request $request): void
    {
        $storeId = (int) Auth::storeId();
        $from = $request->trimmed('from') ?: date('Y-m-01');
        $to = $request->trimmed('to') ?: date('Y-m-d');

        $byMethod = Database::all(
            "SELECT p.method, COUNT(DISTINCT p.sale_id) AS cnt, COALESCE(SUM(p.amount),0) AS total
             FROM payments p JOIN sales s ON s.id = p.sale_id
             WHERE s.store_id = ? AND s.status = 'completed' AND DATE(p.created_at) BETWEEN ? AND ?
             GROUP BY p.method",
            [$storeId, $from, $to]
        );
        $byMethod = array_column($byMethod, null, 'method');

        $sources = [];
        foreach (PaymentMethodService::KEYS as $key) {
            $row = $byMethod[$key] ?? null;
            $note = $key === 'utang'
                ? 'Store credit extended — counted as income now, not again when collected'
                : 'Sales paid immediately via ' . (PaymentMethodService::all($storeId)[$key]['name'] ?? $key);
            $sources[] = [
                'label' => self::METHOD_LABELS[$key],
                'note' => $note,
                'count' => (int) ($row['cnt'] ?? 0),
                'total' => (float) ($row['total'] ?? 0),
            ];
        }

        // Sums both the legacy manual-entry table (eload_records, kept for
        // historical data — see EloadController) and the current
        // product-based eload_transactions, so nothing already recorded is
        // lost and every new E-Load sale counts here exactly once. Only the
        // margin (earnings = selling_price - store_cost) counts as income,
        // regardless of payment method used (including Utang) — the same
        // way this line already worked before product-based E-Load existed.
        $eloadProfit = Database::one(
            "SELECT
                (SELECT COUNT(*) FROM eload_records WHERE store_id = ? AND DATE(transacted_at) BETWEEN ? AND ?)
                + (SELECT COUNT(*) FROM eload_transactions WHERE store_id = ? AND status = 'completed' AND DATE(created_at) BETWEEN ? AND ?) AS cnt,
                (SELECT COALESCE(SUM(profit),0) FROM eload_records WHERE store_id = ? AND DATE(transacted_at) BETWEEN ? AND ?)
                + (SELECT COALESCE(SUM(earnings),0) FROM eload_transactions WHERE store_id = ? AND status = 'completed' AND DATE(created_at) BETWEEN ? AND ?) AS total",
            [$storeId, $from, $to, $storeId, $from, $to, $storeId, $from, $to, $storeId, $from, $to]
        );
        $sources[] = ['label' => 'E-Load Earnings', 'note' => 'Profit margin earned on E-Load transactions', 'count' => (int) $eloadProfit['cnt'], 'total' => (float) $eloadProfit['total']];

        $gcashCharges = Database::one(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(service_charge),0) AS total FROM gcash_records
             WHERE store_id = ? AND DATE(transacted_at) BETWEEN ? AND ?",
            [$storeId, $from, $to]
        );
        $sources[] = ['label' => 'GCash Service Charges', 'note' => 'Charges earned from Cash-In / Cash-Out (separate from GCash used as a POS payment method)', 'count' => (int) $gcashCharges['cnt'], 'total' => (float) $gcashCharges['total']];

        $legacyIncome = Database::one(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM income_records
             WHERE store_id = ? AND income_date BETWEEN ? AND ?",
            [$storeId, $from, $to]
        );
        if ((int) $legacyIncome['cnt'] > 0) {
            $sources[] = ['label' => 'Other (Legacy Manual Entries)', 'note' => 'Recorded before Income became a summary-only view', 'count' => (int) $legacyIncome['cnt'], 'total' => (float) $legacyIncome['total']];
        }

        $utangCollected = Database::one(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM utang_payments
             WHERE store_id = ? AND DATE(created_at) BETWEEN ? AND ?",
            [$storeId, $from, $to]
        );

        $this->view('income/index', [
            'pageTitle' => 'Income Summary',
            'sources' => $sources,
            'totalIncome' => array_sum(array_column($sources, 'total')),
            'utangCollectedCount' => (int) $utangCollected['cnt'],
            'utangCollectedTotal' => (float) $utangCollected['total'],
            'from' => $from,
            'to' => $to,
        ]);
    }
}
