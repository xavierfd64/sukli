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
    public function index(Request $request): void
    {
        $storeId = Auth::storeId();

        $today = date('Y-m-d');

        $salesToday = Database::one(
            "SELECT COALESCE(SUM(total),0) AS total, COUNT(*) AS cnt FROM sales
             WHERE store_id = ? AND status = 'completed' AND DATE(created_at) = ?",
            [$storeId, $today]
        );
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $salesYesterday = Database::one(
            "SELECT COALESCE(SUM(total),0) AS total FROM sales WHERE store_id = ? AND status = 'completed' AND DATE(created_at) = ?",
            [$storeId, $yesterday]
        );

        $incomeToday = Database::one(
            "SELECT COALESCE(SUM(amount),0) AS total FROM income_records WHERE store_id = ? AND income_date = ?",
            [$storeId, $today]
        );
        $incomeYesterday = Database::one(
            "SELECT COALESCE(SUM(amount),0) AS total FROM income_records WHERE store_id = ? AND income_date = ?",
            [$storeId, $yesterday]
        );

        $expenseToday = Database::one(
            "SELECT COALESCE(SUM(amount),0) AS total FROM expense_records WHERE store_id = ? AND expense_date = ?",
            [$storeId, $today]
        );
        $expenseYesterday = Database::one(
            "SELECT COALESCE(SUM(amount),0) AS total FROM expense_records WHERE store_id = ? AND expense_date = ?",
            [$storeId, $yesterday]
        );

        $totalIncomeToday = (float) $salesToday['total'] + (float) $incomeToday['total'];
        $totalIncomeYesterday = (float) $salesYesterday['total'] + (float) $incomeYesterday['total'];
        $netToday = $totalIncomeToday - (float) $expenseToday['total'];
        $netYesterday = $totalIncomeYesterday - (float) $expenseYesterday['total'];

        $paymentSummary = Database::all(
            "SELECT payment_method, COALESCE(SUM(total),0) AS total FROM sales
             WHERE store_id = ? AND status = 'completed' AND DATE(created_at) = ?
             GROUP BY payment_method",
            [$storeId, $today]
        );

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

        $lowStock = Database::all(
            "SELECT id, name, current_stock FROM products
             WHERE store_id = ? AND status = 'active' AND current_stock <= min_stock
             ORDER BY current_stock ASC LIMIT 5",
            [$storeId]
        );

        $recentTransactions = $this->recentTransactions($storeId);

        $topProducts = Database::all(
            "SELECT si.product_name, SUM(si.quantity) AS qty
             FROM sale_items si JOIN sales s ON s.id = si.sale_id
             WHERE s.store_id = ? AND s.status = 'completed'
             GROUP BY si.product_name ORDER BY qty DESC LIMIT 5",
            [$storeId]
        );

        $this->view('dashboard/index', [
            'pageTitle' => 'Dashboard',
            'salesToday' => (float) $salesToday['total'],
            'salesDelta' => self::pctDelta((float) $salesToday['total'], (float) $salesYesterday['total']),
            'incomeToday' => $totalIncomeToday,
            'incomeDelta' => self::pctDelta($totalIncomeToday, $totalIncomeYesterday),
            'expenseToday' => (float) $expenseToday['total'],
            'expenseDelta' => self::pctDelta((float) $expenseToday['total'], (float) $expenseYesterday['total']),
            'netToday' => $netToday,
            'netDelta' => self::pctDelta($netToday, $netYesterday),
            'paymentSummary' => $paymentSummary,
            'cashOnHand' => (float) $cashOnHand['total'],
            'gcashBalance' => (float) $gcashCashIn['total'] - (float) $gcashCashOut['total'],
            'lowStock' => $lowStock,
            'recentTransactions' => $recentTransactions,
            'topProducts' => $topProducts,
            'features' => FeatureService::all((int) $storeId),
        ]);
    }

    private function recentTransactions(?int $storeId): array
    {
        $rows = Database::all(
            "(SELECT 'Sale' AS type, CONCAT('POS Sale #', sale_number) AS description, total AS amount, created_at FROM sales WHERE store_id = ? AND status='completed')
             UNION ALL
             (SELECT 'Expense', category, amount, created_at FROM expense_records WHERE store_id = ?)
             UNION ALL
             (SELECT 'Income', category, amount, created_at FROM income_records WHERE store_id = ?)
             UNION ALL
             (SELECT 'E-Load', CONCAT(COALESCE(network,'Load'), ' - ', COALESCE(customer_name,'Walk-in')), load_amount, created_at FROM eload_records WHERE store_id = ?)
             UNION ALL
             (SELECT IF(type='cash_in','GCash In','GCash Out'), COALESCE(customer_reference,'GCash'), amount, created_at FROM gcash_records WHERE store_id = ?)
             UNION ALL
             (SELECT 'Payment', CONCAT('Utang Payment'), amount, created_at FROM utang_payments WHERE store_id = ?)
             ORDER BY created_at DESC LIMIT 8",
            [$storeId, $storeId, $storeId, $storeId, $storeId, $storeId]
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
