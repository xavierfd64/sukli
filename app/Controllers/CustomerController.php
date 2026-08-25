<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Core\Session;
use Sukli\Services\AuditService;
use Sukli\Services\CustomerSearchService;

class CustomerController extends Controller
{
    public function index(Request $request): void
    {
        $storeId = Auth::storeId();
        $search = $request->trimmed('q');

        $where = 'c.store_id = ?';
        $params = [$storeId];
        if ($search !== '') {
            $where .= ' AND ' . CustomerSearchService::whereFragment('c');
            $params = array_merge($params, CustomerSearchService::params($search));
        }

        $customers = Database::all(
            "SELECT c.*, COALESCE(cca.outstanding_balance, 0) AS outstanding_balance
             FROM customers c
             LEFT JOIN customer_credit_accounts cca ON cca.customer_id = c.id
             WHERE {$where} ORDER BY c.first_name ASC, c.last_name ASC",
            $params
        );

        $this->view('customers/index', [
            'pageTitle' => 'Customers',
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    /** JSON typeahead used by the POS Utang combobox, E-Load, and GCash customer search. */
    public function search(Request $request): void
    {
        $storeId = Auth::storeId();
        $term = $request->trimmed('q');

        if ($term === '') {
            $this->json(['customers' => []]);
        }

        $rows = Database::all(
            "SELECT id, first_name, last_name, contact_number FROM customers
             WHERE store_id = ? AND status = 'active' AND " . CustomerSearchService::whereFragment('') . "
             ORDER BY first_name ASC LIMIT 8",
            array_merge([$storeId], CustomerSearchService::params($term))
        );

        $customers = array_map(fn ($c) => [
            'id' => (int) $c['id'],
            'name' => CustomerSearchService::fullName($c),
            'contact_number' => $c['contact_number'],
        ], $rows);

        $this->json(['customers' => $customers]);
    }

    public function exportCsv(Request $request): void
    {
        $storeId = Auth::storeId();
        $search = $request->trimmed('q');

        $where = 'c.store_id = ?';
        $params = [$storeId];
        if ($search !== '') {
            $where .= ' AND ' . CustomerSearchService::whereFragment('c');
            $params = array_merge($params, CustomerSearchService::params($search));
        }

        $customers = Database::all(
            "SELECT c.first_name, c.last_name, c.contact_number, c.address, c.status, COALESCE(cca.outstanding_balance, 0) AS outstanding_balance
             FROM customers c LEFT JOIN customer_credit_accounts cca ON cca.customer_id = c.id
             WHERE {$where} ORDER BY c.first_name ASC",
            $params
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sukli-customers-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['first_name', 'last_name', 'contact_number', 'address', 'status', 'outstanding_balance'], ',', '"', '\\');
        foreach ($customers as $row) {
            fputcsv($out, $row, ',', '"', '\\');
        }
        fclose($out);
        exit;
    }

    public function store(Request $request): void
    {
        $storeId = Auth::storeId();
        $firstName = $request->trimmed('first_name');
        if ($firstName === '') {
            Session::flash('error', 'Customer first name is required.');
            $this->back('/customers');
        }

        Database::execute(
            "INSERT INTO customers (store_id, first_name, last_name, contact_number, address, notes, status) VALUES (?, ?, ?, ?, ?, ?, 'active')",
            [$storeId, $firstName, $request->trimmed('last_name') ?: null, $request->trimmed('contact_number') ?: null,
             $request->trimmed('address') ?: null, $request->trimmed('notes') ?: null]
        );
        $id = (int) Database::lastInsertId();
        Database::execute(
            "INSERT INTO customer_credit_accounts (store_id, customer_id, outstanding_balance) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE store_id = store_id",
            [$storeId, $id]
        );

        AuditService::log('create', 'customers', 'customer', $id, null, ['first_name' => $firstName]);
        Session::flash('success', 'Customer added.');
        $this->back('/customers');
    }

    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();
        $existing = Database::one("SELECT id FROM customers WHERE id = ? AND store_id = ?", [$id, $storeId]);
        if (!$existing) {
            Session::flash('error', 'Customer not found.');
            $this->back('/customers');
        }

        if ($request->input('_action') === 'toggle_status') {
            $current = Database::one("SELECT status FROM customers WHERE id = ?", [$id]);
            $newStatus = $current['status'] === 'active' ? 'inactive' : 'active';
            Database::execute("UPDATE customers SET status = ? WHERE id = ?", [$newStatus, $id]);
            AuditService::log($newStatus === 'active' ? 'restore' : 'archive', 'customers', 'customer', $id);
            Session::flash('success', 'Customer status updated.');
            $this->back('/customers');
        }

        Database::execute(
            "UPDATE customers SET first_name=?, last_name=?, contact_number=?, address=?, notes=?, updated_at=NOW() WHERE id = ? AND store_id = ?",
            [$request->trimmed('first_name'), $request->trimmed('last_name') ?: null, $request->trimmed('contact_number') ?: null,
             $request->trimmed('address') ?: null, $request->trimmed('notes') ?: null, $id, $storeId]
        );

        AuditService::log('update', 'customers', 'customer', $id);
        Session::flash('success', 'Customer updated.');
        $this->back('/customers');
    }
}
