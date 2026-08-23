<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Core\Session;
use Sukli\Services\AuditService;

class CustomerController extends Controller
{
    public function index(Request $request): void
    {
        $storeId = Auth::storeId();
        $search = $request->trimmed('q');

        $where = 'c.store_id = ?';
        $params = [$storeId];
        if ($search !== '') {
            $where .= ' AND (c.name LIKE ? OR c.contact_number LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $customers = Database::all(
            "SELECT c.*, COALESCE(cca.outstanding_balance, 0) AS outstanding_balance
             FROM customers c
             LEFT JOIN customer_credit_accounts cca ON cca.customer_id = c.id
             WHERE {$where} ORDER BY c.name ASC",
            $params
        );

        $this->view('customers/index', [
            'pageTitle' => 'Customers',
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    public function store(Request $request): void
    {
        $storeId = Auth::storeId();
        $name = $request->trimmed('name');
        if ($name === '') {
            Session::flash('error', 'Customer name is required.');
            $this->back('/customers');
        }

        Database::execute(
            "INSERT INTO customers (store_id, name, contact_number, address, notes, status) VALUES (?, ?, ?, ?, ?, 'active')",
            [$storeId, $name, $request->trimmed('contact_number') ?: null, $request->trimmed('address') ?: null, $request->trimmed('notes') ?: null]
        );
        $id = (int) Database::lastInsertId();
        Database::execute(
            "INSERT INTO customer_credit_accounts (store_id, customer_id, outstanding_balance) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE store_id = store_id",
            [$storeId, $id]
        );

        AuditService::log('create', 'customers', 'customer', $id, null, ['name' => $name]);
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
            Session::flash('success', 'Customer status updated.');
            $this->back('/customers');
        }

        Database::execute(
            "UPDATE customers SET name=?, contact_number=?, address=?, notes=?, updated_at=NOW() WHERE id = ? AND store_id = ?",
            [$request->trimmed('name'), $request->trimmed('contact_number') ?: null, $request->trimmed('address') ?: null,
             $request->trimmed('notes') ?: null, $id, $storeId]
        );

        AuditService::log('update', 'customers', 'customer', $id);
        Session::flash('success', 'Customer updated.');
        $this->back('/customers');
    }
}
