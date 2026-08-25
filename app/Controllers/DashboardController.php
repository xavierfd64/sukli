<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Services\FeatureService;

class DashboardController extends Controller
{
    private const PERIODS = ['today', 'week', 'month', 'year', 'custom'];

    public function index(Request $request): void
    {
        $storeId = Auth::storeId();

        $period = $request->input('period', 'today');
        if (!in_array($period, self::PERIODS, true)) {
            $period = 'today';
        }

        [$from, $to] = $this->resolvePeriod($period, $request->trimmed('from'), $request->trimmed('to'));
        [$prevFrom, $prevTo] = $this->previousPeriod($from, $to);

        $sales = Database::one(
            "SELECT COALESCE(SUM(total),0) AS total, COUNT(*) AS cnt FROM sales
             WHERE store_id = ? AND status = 'completed' AND DATE(created_at) BETWEEN ? AND ?",
            [$storeId, $from, $to]
        );
        $salesPrev = Database::one(
            "SELECT COALESCE(SUM(total),0) AS total FROM sales WHERE store_id = ? AND status = 'completed' AND DATE(created_at) BETWEEN ? AND ?",
            [$storeId, $prevFrom, $prevTo]
        );

        $income = Database::one(
            "SELECT COALESCE(SUM(amount),0) AS total FROM income_records WHERE store_id = ? AND income_date BETWEEN ? AND ?",
            [$storeId, $from, $to]
        );
        $incomePrev = Database::one(
            "SELECT COALESCE(SUM(amount),0) AS total FROM income_records WHERE store_id = ? AND income_date BETWEEN ? AND ?",
            [$storeId, $prevFrom, $prevTo]
        );

        $expense = Database::one(
            "SELECT COALESCE(SUM(amount),0) AS total FROM expense_records WHERE store_id = ? AND expense_date BETWEEN ? AND ?",
            [$storeId, $from, $to]
        );
        $expensePrev = Database::one(
            "SELECT COALESCE(SUM(amount),0) AS total FROM expense_records WHERE store_id = ? AND expense_date BETWEEN ? AND ?",
            [$storeId, $prevFrom, $prevTo]
        );

        $totalIncome = (float) $sales['total'] + (float) $income['total'];
        $totalIncomePrev = (float) $salesPrev['total'] + (float) $incomePrev['total'];
        $net = $totalIncome - (float) $expense['total'];
        $netPrev = $totalIncomePrev - (float) $expensePrev['total'];

        $paymentSummary = Database::all(
            "SELECT payment_method, COALESCE(SUM(total),0) AS total FROM sales
             WHERE store_id = ? AND status = 'completed' AND DATE(created_at) BETWEEN ? AND ?
             GROUP BY payment_method",
            [$storeId, $from, $to]
        );

        // Running balances (all-time), not period-scoped — a "balance" is a
        // current-state snapshot, not an activity total for the period.
        $cashOnHand = Database::one(
            "SELECT COALESCE(SUM(total),0) AS total FROM sales WHERE store_id = ? AND status = 'completed' AND payment_method = 'cash'",
            [$storeId]
        );
        $gcashCashIn = Database::one(
            "SELECT COALESCE(SUM(amount),0) AS total FROM gcash_records WHERE store_id = ? AND type = 'cash_in'",
            [$storeId]
        );
        $gcashCashOut = Database::one(
            "SELECT COALESCE(SUM(amount),0) AS total FROM gcash_records WHERE store_id = ? AND type = 'cash_out'",
            [$storeId]
        );

        // Current inventory status — always "now", never period-filtered.
        $lowStock = Database::all(
            "SELECT id, name, current_stock FROM products
             WHERE store_id = ? AND status = 'active' AND current_stock <= min_stock
             ORDER BY current_stock ASC LIMIT 5",
            [$storeId]
        );

        $recentTransactions = $this->recentTransactions($storeId, $from, $to);

        $topProducts = Database::all(
            "SELECT si.product_name, SUM(si.quantity) AS qty
             FROM sale_items si JOIN sales s ON s.id = si.sale_id
             WHERE s.store_id = ? AND s.status = 'completed' AND DATE(s.created_at) BETWEEN ? AND ?
             GROUP BY si.product_name ORDER BY qty DESC LIMIT 5",
            [$storeId, $from, $to]
        );

        $this->view('dashboard/index', [
            'pageTitle' => 'Dashboard',
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'periodLabel' => $this->periodLabel($period, $from, $to),
            'salesToday' => (float) $sales['total'],
            'salesDelta' => self::pctDelta((float) $sales['total'], (float) $salesPrev['total']),
            'incomeToday' => $totalIncome,
            'incomeDelta' => self::pctDelta($totalIncome, $totalIncomePrev),
            'expenseToday' => (float) $expense['total'],
            'expenseDelta' => self::pctDelta((float) $expense['total'], (float) $expensePrev['total']),
            'netToday' => $net,
            'netDelta' => self::pctDelta($net, $netPrev),
            'paymentSummary' => $paymentSummary,
            'cashOnHand' => (float) $cashOnHand['total'],
            'gcashBalance' => (float) $gcashCashIn['total'] - (float) $gcashCashOut['total'],
            'lowStock' => $lowStock,
            'recentTransactions' => $recentTransactions,
            'topProducts' => $topProducts,
            'features' => FeatureService::all((int) $storeId),
        ]);
    }

    /** @return array{0:string,1:string} [from, to] as Y-m-d, in the store's timezone (already applied globally). */
    private function resolvePeriod(string $period, string $customFrom, string $customTo): array
    {
        $today = date('Y-m-d');

        return match ($period) {
            'week' => [date('Y-m-d', strtotime('monday this week')), $today],
            'month' => [date('Y-m-01'), $today],
            'year' => [date('Y-01-01'), $today],
            'custom' => [
                $customFrom ?: $today,
                $customTo ?: $today,
            ],
            default => [$today, $today],
        };
    }

    /** @return array{0:string,1:string} The immediately preceding period of equal length, for the delta comparison. */
    private function previousPeriod(string $from, string $to): array
    {
        $days = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
        $prevTo = date('Y-m-d', strtotime($from . ' -1 day'));
        $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));
        return [$prevFrom, $prevTo];
    }

    private function periodLabel(string $period, string $from, string $to): string
    {
        return match ($period) {
            'today' => 'Today',
            'week' => 'This Week',
            'month' => 'This Month',
            'year' => 'This Year',
            'custom' => date('M d, Y', strtotime($from)) . ' – ' . date('M d, Y', strtotime($to)),
            default => 'Today',
        };
    }

    private function recentTransactions(?int $storeId, string $from, string $to): array
    {
        $rows = Database::all(
            "(SELECT 'Sale' AS type, CONCAT('POS Sale #', sale_number) AS description, total AS amount, created_at FROM sales WHERE store_id = ? AND status='completed' AND DATE(created_at) BETWEEN ? AND ?)
             UNION ALL
             (SELECT 'Expense', category, amount, created_at FROM expense_records WHERE store_id = ? AND expense_date BETWEEN ? AND ?)
             UNION ALL
             (SELECT 'Income', category, amount, created_at FROM income_records WHERE store_id = ? AND income_date BETWEEN ? AND ?)
             UNION ALL
             (SELECT 'E-Load', CONCAT(COALESCE(network,'Load'), ' - ', COALESCE(customer_name,'Walk-in')), load_amount, created_at FROM eload_records WHERE store_id = ? AND DATE(created_at) BETWEEN ? AND ?)
             UNION ALL
             (SELECT 'E-Load', CONCAT(COALESCE(network,'Load'), ' - ', COALESCE(customer_name,'Walk-in')), selling_price, created_at FROM eload_transactions WHERE store_id = ? AND status='completed' AND DATE(created_at) BETWEEN ? AND ?)
             UNION ALL
             (SELECT IF(type='cash_in','GCash In','GCash Out'), COALESCE(customer_reference,'GCash'), amount, created_at FROM gcash_records WHERE store_id = ? AND DATE(created_at) BETWEEN ? AND ?)
             UNION ALL
             (SELECT 'Payment', CONCAT('Utang Payment'), amount, created_at FROM utang_payments WHERE store_id = ? AND DATE(created_at) BETWEEN ? AND ?)
             ORDER BY created_at DESC LIMIT 8",
            [
                $storeId, $from, $to, $storeId, $from, $to, $storeId, $from, $to,
                $storeId, $from, $to, $storeId, $from, $to, $storeId, $from, $to, $storeId, $from, $to,
            ]
        );
        return $rows;
    }

    private static function pctDelta(float $today, float $yesterday): float
    {
        if ($yesterday <= 0) {
            return $today > 0 ? 100.0 : 0.0;
        }
        return round((($today - $yesterday) / $yesterday) * 100, 1);
    }
}
