<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;

/**
 * Income is a summary-only view — it auto-aggregates from POS sales,
 * E-Load profit, and GCash service charges rather than accepting manual
 * entries. Historical rows in income_records (from before this change)
 * are still shown, read-only, so nothing entered previously is lost.
 */
class IncomeController extends Controller
{
    public function index(Request $request): void
    {
        $storeId = Auth::storeId();
        $from = $request->trimmed('from') ?: date('Y-m-01');
        $to = $request->trimmed('to') ?: date('Y-m-d');

        $posSales = Database::one(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS total FROM sales
             WHERE store_id = ? AND status = 'completed' AND DATE(created_at) BETWEEN ? AND ?",
            [$storeId, $from, $to]
        );
        $eloadProfit = Database::one(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(profit),0) AS total FROM eload_records
             WHERE store_id = ? AND DATE(transacted_at) BETWEEN ? AND ?",
            [$storeId, $from, $to]
        );
        $gcashCharges = Database::one(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(service_charge),0) AS total FROM gcash_records
             WHERE store_id = ? AND DATE(transacted_at) BETWEEN ? AND ?",
            [$storeId, $from, $to]
        );
        $legacyIncome = Database::one(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM income_records
             WHERE store_id = ? AND income_date BETWEEN ? AND ?",
            [$storeId, $from, $to]
        );

        $sources = [
            ['label' => 'POS Sales Revenue', 'note' => 'Completed sales in this period', 'count' => (int) $posSales['cnt'], 'total' => (float) $posSales['total']],
            ['label' => 'E-Load Profit', 'note' => 'Profit margin earned on E-Load transactions', 'count' => (int) $eloadProfit['cnt'], 'total' => (float) $eloadProfit['total']],
            ['label' => 'GCash Service Charges', 'note' => 'Charges earned from Cash-In / Cash-Out', 'count' => (int) $gcashCharges['cnt'], 'total' => (float) $gcashCharges['total']],
        ];
        if ((int) $legacyIncome['cnt'] > 0) {
            $sources[] = ['label' => 'Other (Legacy Manual Entries)', 'note' => 'Recorded before Income became a summary-only view', 'count' => (int) $legacyIncome['cnt'], 'total' => (float) $legacyIncome['total']];
        }

        $this->view('income/index', [
            'pageTitle' => 'Income Summary',
            'sources' => $sources,
            'totalIncome' => array_sum(array_column($sources, 'total')),
            'from' => $from,
            'to' => $to,
        ]);
    }
}
