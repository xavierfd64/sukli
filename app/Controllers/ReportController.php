<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Services\FeatureService;

class ReportController extends Controller
{
    private const REPORTS = [
        'sales' => 'Sales',
        'income' => 'Income Summary',
        'expense' => 'Expense Summary',
        'net' => 'Net Income Summary',
        'low_stock' => 'Low Stock',
        'inventory_value' => 'Inventory Value',
        'eload' => 'E-Load Records',
        'gcash' => 'GCash Records',
        'utang_balances' => 'Utang Balances',
        'utang_payments' => 'Utang Payments',
    ];

    public function index(Request $request): void
    {
        $storeId = (int) Auth::storeId();
        $features = FeatureService::all($storeId);

        $report = $request->input('report', 'sales');
        if (!array_key_exists($report, self::REPORTS)) {
            $report = 'sales';
        }
        $from = $request->trimmed('from') ?: date('Y-m-01');
        $to = $request->trimmed('to') ?: date('Y-m-d');

        $data = match ($report) {
            'sales' => $this->salesReport($storeId, $from, $to),
            'income' => $this->incomeReport($storeId, $from, $to),
            'expense' => $this->expenseReport($storeId, $from, $to),
            'net' => $this->netReport($storeId, $from, $to),
            'low_stock' => $this->lowStockReport($storeId),
            'inventory_value' => $this->inventoryValueReport($storeId),
            'eload' => $this->eloadReport($storeId, $from, $to),
            'gcash' => $this->gcashReport($storeId, $from, $to),
            'utang_balances' => $this->utangBalancesReport($storeId),
            'utang_payments' => $this->utangPaymentsReport($storeId, $from, $to),
            default => [],
        };

        $availableReports = self::REPORTS;
        if (empty($features['eload']['is_enabled'])) unset($availableReports['eload']);
        if (empty($features['gcash']['is_enabled'])) unset($availableReports['gcash']);
        if (empty($features['utang']['is_enabled'])) { unset($availableReports['utang_balances']); unset($availableReports['utang_payments']); }

        $this->view('reports/index', [
            'pageTitle' => 'Reports',
            'reports' => $availableReports,
            'report' => $report,
            'reportLabel' => self::REPORTS[$report],
            'from' => $from,
            'to' => $to,
            'data' => $data,
        ]);
    }

    private function salesReport(int $storeId, string $from, string $to): array
    {
        $rows = Database::all(
            "SELECT DATE(created_at) AS day, COUNT(*) AS transactions, COALESCE(SUM(total),0) AS total
             FROM sales WHERE store_id = ? AND status='completed' AND DATE(created_at) BETWEEN ? AND ?
             GROUP BY DATE(created_at) ORDER BY day DESC",
            [$storeId, $from, $to]
        );
        return ['rows' => $rows, 'total' => array_sum(array_column($rows, 'total'))];
    }

    private function incomeReport(int $storeId, string $from, string $to): array
    {
        $rows = Database::all(
            "SELECT category, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM income_records
             WHERE store_id = ? AND income_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC",
            [$storeId, $from, $to]
        );
        return ['rows' => $rows, 'total' => array_sum(array_column($rows, 'total'))];
    }

    private function expenseReport(int $storeId, string $from, string $to): array
    {
        $rows = Database::all(
            "SELECT category, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM expense_records
             WHERE store_id = ? AND expense_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC",
            [$storeId, $from, $to]
        );
        return ['rows' => $rows, 'total' => array_sum(array_column($rows, 'total'))];
    }

    private function netReport(int $storeId, string $from, string $to): array
    {
        $sales = Database::one("SELECT COALESCE(SUM(total),0) AS t FROM sales WHERE store_id=? AND status='completed' AND DATE(created_at) BETWEEN ? AND ?", [$storeId, $from, $to]);
        $income = Database::one("SELECT COALESCE(SUM(amount),0) AS t FROM income_records WHERE store_id=? AND income_date BETWEEN ? AND ?", [$storeId, $from, $to]);
        $expense = Database::one("SELECT COALESCE(SUM(amount),0) AS t FROM expense_records WHERE store_id=? AND expense_date BETWEEN ? AND ?", [$storeId, $from, $to]);

        $totalIncome = (float) $sales['t'] + (float) $income['t'];
        return [
            'sales' => (float) $sales['t'],
            'other_income' => (float) $income['t'],
            'total_income' => $totalIncome,
            'expenses' => (float) $expense['t'],
            'net' => $totalIncome - (float) $expense['t'],
        ];
    }

    private function lowStockReport(int $storeId): array
    {
        $rows = Database::all(
            "SELECT name, current_stock, min_stock, unit FROM products
             WHERE store_id = ? AND status='active' AND current_stock <= min_stock ORDER BY current_stock ASC",
            [$storeId]
        );
        return ['rows' => $rows];
    }

    private function inventoryValueReport(int $storeId): array
    {
        $rows = Database::all(
            "SELECT name, current_stock, cost_price, selling_price, (current_stock * cost_price) AS cost_value, (current_stock * selling_price) AS retail_value
             FROM products WHERE store_id = ? AND status='active' ORDER BY cost_value DESC",
            [$storeId]
        );
        return [
            'rows' => $rows,
            'total_cost_value' => array_sum(array_column($rows, 'cost_value')),
            'total_retail_value' => array_sum(array_column($rows, 'retail_value')),
        ];
    }

    private function eloadReport(int $storeId, string $from, string $to): array
    {
        $rows = Database::all(
            "SELECT * FROM eload_records WHERE store_id = ? AND DATE(transacted_at) BETWEEN ? AND ? ORDER BY transacted_at DESC",
            [$storeId, $from, $to]
        );
        return [
            'rows' => $rows,
            'total_load' => array_sum(array_column($rows, 'load_amount')),
            'total_profit' => array_sum(array_column($rows, 'profit')),
        ];
    }

    private function gcashReport(int $storeId, string $from, string $to): array
    {
        $rows = Database::all(
            "SELECT * FROM gcash_records WHERE store_id = ? AND DATE(transacted_at) BETWEEN ? AND ? ORDER BY transacted_at DESC",
            [$storeId, $from, $to]
        );
        $cashIn = array_sum(array_map(fn ($r) => $r['type'] === 'cash_in' ? (float) $r['amount'] : 0, $rows));
        $cashOut = array_sum(array_map(fn ($r) => $r['type'] === 'cash_out' ? (float) $r['amount'] : 0, $rows));
        return ['rows' => $rows, 'cash_in' => $cashIn, 'cash_out' => $cashOut];
    }

    private function utangBalancesReport(int $storeId): array
    {
        $rows = Database::all(
            "SELECT c.name, cca.outstanding_balance FROM customer_credit_accounts cca
             JOIN customers c ON c.id = cca.customer_id
             WHERE cca.store_id = ? AND cca.outstanding_balance > 0 ORDER BY cca.outstanding_balance DESC",
            [$storeId]
        );
        return ['rows' => $rows, 'total' => array_sum(array_column($rows, 'outstanding_balance'))];
    }

    private function utangPaymentsReport(int $storeId, string $from, string $to): array
    {
        $rows = Database::all(
            "SELECT up.*, c.name AS customer_name FROM utang_payments up
             JOIN customers c ON c.id = up.customer_id
             WHERE up.store_id = ? AND DATE(up.created_at) BETWEEN ? AND ? ORDER BY up.created_at DESC",
            [$storeId, $from, $to]
        );
        return ['rows' => $rows, 'total' => array_sum(array_column($rows, 'amount'))];
    }
}
