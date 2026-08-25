<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Services\CustomerSearchService;
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
        'customers' => 'Customers',
        'suppliers' => 'Suppliers',
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
            'data' => $this->generateReport($report, $storeId, $from, $to),
        ]);
    }

    public function exportCsv(Request $request): void
    {
        $storeId = (int) Auth::storeId();
        $report = $request->input('report', 'sales');
        if (!array_key_exists($report, self::REPORTS)) {
            $report = 'sales';
        }
        $from = $request->trimmed('from') ?: date('Y-m-01');
        $to = $request->trimmed('to') ?: date('Y-m-d');
        $data = $this->generateReport($report, $storeId, $from, $to);

        $columns = match ($report) {
            'sales' => ['day' => 'Date', 'transactions' => 'Transactions', 'total' => 'Total'],
            'income' => ['category' => 'Category', 'cnt' => 'Count', 'total' => 'Total'],
            'expense' => ['category' => 'Category', 'cnt' => 'Count', 'total' => 'Total'],
            'low_stock' => ['name' => 'Product', 'current_stock' => 'Current Stock', 'min_stock' => 'Min Stock', 'unit' => 'Unit'],
            'inventory_value' => ['name' => 'Product', 'supplier' => 'Supplier', 'current_stock' => 'Stock', 'cost_value' => 'Cost Value', 'retail_value' => 'Retail Value'],
            'eload' => ['transacted_at' => 'Date', 'customer_name' => 'Customer', 'network' => 'Network', 'load_amount' => 'Load', 'amount_received' => 'Received', 'profit' => 'Profit'],
            'gcash' => ['transacted_at' => 'Date', 'type' => 'Type', 'amount' => 'Amount', 'service_charge' => 'Service Charge', 'customer_reference' => 'Reference'],
            'utang_balances' => ['name' => 'Customer', 'outstanding_balance' => 'Outstanding Balance'],
            'utang_payments' => ['created_at' => 'Date', 'customer_name' => 'Customer', 'amount' => 'Amount', 'payment_method' => 'Method'],
            'customers' => ['name' => 'Name', 'contact_number' => 'Contact Number', 'status' => 'Status', 'outstanding_balance' => 'Outstanding Balance'],
            'suppliers' => ['display_name' => 'Supplier', 'contact_person' => 'Contact Person', 'contact_number' => 'Contact Number', 'address' => 'Address', 'status' => 'Status'],
            default => [],
        };

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sukli-' . $report . '-' . date('Ymd-His') . '.csv"');

        $out = fopen('php://output', 'w');
        if ($report === 'net') {
            fputcsv($out, ['Metric', 'Amount'], ',', '"', '\\');
            fputcsv($out, ['POS Sales', $data['sales']], ',', '"', '\\');
            fputcsv($out, ['Other Income', $data['other_income']], ',', '"', '\\');
            fputcsv($out, ['Total Income', $data['total_income']], ',', '"', '\\');
            fputcsv($out, ['Expenses', $data['expenses']], ',', '"', '\\');
            fputcsv($out, ['Net Income', $data['net']], ',', '"', '\\');
        } else {
            fputcsv($out, array_values($columns), ',', '"', '\\');
            foreach (($data['rows'] ?? []) as $row) {
                fputcsv($out, array_map(fn ($key) => $row[$key] ?? '', array_keys($columns)), ',', '"', '\\');
            }
        }
        fclose($out);
        exit;
    }

    private function generateReport(string $report, int $storeId, string $from, string $to): array
    {
        return match ($report) {
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
            'customers' => $this->customersReport($storeId),
            'suppliers' => $this->suppliersReport($storeId),
            default => [],
        };
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
            "SELECT p.name, p.current_stock, p.cost_price, p.selling_price,
                    (p.current_stock * p.cost_price) AS cost_value, (p.current_stock * p.selling_price) AS retail_value,
                    s.company_name AS supplier_company, s.contact_first_name AS supplier_first_name, s.contact_last_name AS supplier_last_name
             FROM products p LEFT JOIN suppliers s ON s.id = p.supplier_id
             WHERE p.store_id = ? AND p.status='active' ORDER BY cost_value DESC",
            [$storeId]
        );
        foreach ($rows as &$row) {
            $row['supplier'] = ($row['supplier_company'] || $row['supplier_first_name'] || $row['supplier_last_name'])
                ? supplier_display_name(['company_name' => $row['supplier_company'], 'contact_first_name' => $row['supplier_first_name'], 'contact_last_name' => $row['supplier_last_name']])
                : '—';
        }
        return [
            'rows' => $rows,
            'total_cost_value' => array_sum(array_column($rows, 'cost_value')),
            'total_retail_value' => array_sum(array_column($rows, 'retail_value')),
        ];
    }

    /**
     * Combines legacy manual-entry records (eload_records) with the current
     * product-based ones (eload_transactions), normalized to the same
     * column shape the view already renders, so this report keeps showing
     * every E-Load transaction — old and new — in one list.
     */
    private function eloadReport(int $storeId, string $from, string $to): array
    {
        $legacy = Database::all(
            "SELECT transacted_at, customer_name, network, load_amount, amount_received, profit
             FROM eload_records WHERE store_id = ? AND DATE(transacted_at) BETWEEN ? AND ?",
            [$storeId, $from, $to]
        );
        $productBased = Database::all(
            "SELECT created_at AS transacted_at, customer_name, network, load_value AS load_amount, selling_price AS amount_received, earnings AS profit
             FROM eload_transactions WHERE store_id = ? AND status = 'completed' AND DATE(created_at) BETWEEN ? AND ?",
            [$storeId, $from, $to]
        );
        $rows = array_merge($legacy, $productBased);
        usort($rows, static fn (array $a, array $b) => strcmp($b['transacted_at'], $a['transacted_at']));

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
            "SELECT c.first_name, c.last_name, cca.outstanding_balance FROM customer_credit_accounts cca
             JOIN customers c ON c.id = cca.customer_id
             WHERE cca.store_id = ? AND cca.outstanding_balance > 0 ORDER BY cca.outstanding_balance DESC",
            [$storeId]
        );
        foreach ($rows as &$row) {
            $row['name'] = CustomerSearchService::fullName($row);
        }
        return ['rows' => $rows, 'total' => array_sum(array_column($rows, 'outstanding_balance'))];
    }

    private function utangPaymentsReport(int $storeId, string $from, string $to): array
    {
        $rows = Database::all(
            "SELECT up.*, c.first_name, c.last_name FROM utang_payments up
             JOIN customers c ON c.id = up.customer_id
             WHERE up.store_id = ? AND DATE(up.created_at) BETWEEN ? AND ? ORDER BY up.created_at DESC",
            [$storeId, $from, $to]
        );
        foreach ($rows as &$row) {
            $row['customer_name'] = CustomerSearchService::fullName($row);
        }
        return ['rows' => $rows, 'total' => array_sum(array_column($rows, 'amount'))];
    }

    private function customersReport(int $storeId): array
    {
        $rows = Database::all(
            "SELECT c.first_name, c.last_name, c.contact_number, c.status,
                    COALESCE(cca.outstanding_balance, 0) AS outstanding_balance
             FROM customers c
             LEFT JOIN customer_credit_accounts cca ON cca.customer_id = c.id
             WHERE c.store_id = ? ORDER BY c.first_name, c.last_name",
            [$storeId]
        );
        foreach ($rows as &$row) {
            $row['name'] = CustomerSearchService::fullName($row);
        }
        return ['rows' => $rows, 'total' => count($rows)];
    }

    private function suppliersReport(int $storeId): array
    {
        $rows = Database::all(
            "SELECT company_name, contact_first_name, contact_last_name, contact_number, address, status
             FROM suppliers WHERE store_id = ? ORDER BY company_name, contact_first_name",
            [$storeId]
        );
        foreach ($rows as &$row) {
            $contact = trim(($row['contact_first_name'] ?? '') . ' ' . ($row['contact_last_name'] ?? ''));
            $row['display_name'] = $row['company_name'] ?: $contact ?: 'Unnamed Supplier';
            $row['contact_person'] = $contact;
        }
        return ['rows' => $rows, 'total' => count($rows)];
    }
}
