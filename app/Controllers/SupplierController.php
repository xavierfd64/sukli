<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Core\Session;
use Sukli\Services\AuditService;

class SupplierController extends Controller
{
    public function index(Request $request): void
    {
        $storeId = Auth::storeId();
        $suppliers = Database::all("SELECT * FROM suppliers WHERE store_id = ? ORDER BY name", [$storeId]);
        $this->view('suppliers/index', ['pageTitle' => 'Suppliers', 'suppliers' => $suppliers]);
    }

    public function store(Request $request): void
    {
        $storeId = Auth::storeId();
        $name = $request->trimmed('name');
        if ($name === '') {
            Session::flash('error', 'Supplier name is required.');
            $this->back('/suppliers');
        }

        Database::execute(
            "INSERT INTO suppliers (store_id, name, contact_number, address, notes, status) VALUES (?, ?, ?, ?, ?, 'active')",
            [$storeId, $name, $request->trimmed('contact_number') ?: null, $request->trimmed('address') ?: null, $request->trimmed('notes') ?: null]
        );
        $id = (int) Database::lastInsertId();

        AuditService::log('create', 'suppliers', 'supplier', $id, null, ['name' => $name]);
        Session::flash('success', 'Supplier added.');
        $this->back('/suppliers');
    }

    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();
        $existing = Database::one("SELECT id, status FROM suppliers WHERE id = ? AND store_id = ?", [$id, $storeId]);
        if (!$existing) {
            Session::flash('error', 'Supplier not found.');
            $this->back('/suppliers');
        }

        if ($request->input('_action') === 'toggle_status') {
            $newStatus = $existing['status'] === 'active' ? 'inactive' : 'active';
            Database::execute("UPDATE suppliers SET status = ? WHERE id = ?", [$newStatus, $id]);
            Session::flash('success', 'Supplier status updated.');
            $this->back('/suppliers');
        }

        Database::execute(
            "UPDATE suppliers SET name=?, contact_number=?, address=?, notes=?, updated_at=NOW() WHERE id = ? AND store_id = ?",
            [$request->trimmed('name'), $request->trimmed('contact_number') ?: null, $request->trimmed('address') ?: null,
             $request->trimmed('notes') ?: null, $id, $storeId]
        );

        AuditService::log('update', 'suppliers', 'supplier', $id);
        Session::flash('success', 'Supplier updated.');
        $this->back('/suppliers');
    }
}
