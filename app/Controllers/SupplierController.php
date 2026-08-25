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
        $suppliers = Database::all(
            "SELECT s.*, (SELECT COUNT(*) FROM products p WHERE p.supplier_id = s.id) AS product_count
             FROM suppliers s WHERE s.store_id = ? ORDER BY s.company_name, s.contact_first_name",
            [$storeId]
        );
        $this->view('suppliers/index', ['pageTitle' => 'Suppliers', 'suppliers' => $suppliers]);
    }

    public function store(Request $request): void
    {
        $storeId = Auth::storeId();
        $data = $this->validated($request);
        if ($data === null) {
            Session::flash('error', 'Enter at least a company name or a contact person name.');
            $this->back('/suppliers');
        }

        Database::execute(
            "INSERT INTO suppliers (store_id, company_name, contact_first_name, contact_last_name, contact_number, address, notes, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active')",
            [$storeId, $data['company_name'], $data['contact_first_name'], $data['contact_last_name'],
             $data['contact_number'], $data['address'], $data['notes']]
        );
        $id = (int) Database::lastInsertId();

        AuditService::log('create', 'suppliers', 'supplier', $id, null, $data);
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
            AuditService::log($newStatus === 'active' ? 'restore' : 'archive', 'suppliers', 'supplier', $id);
            Session::flash('success', 'Supplier status updated.');
            $this->back('/suppliers');
        }

        $data = $this->validated($request);
        if ($data === null) {
            Session::flash('error', 'Enter at least a company name or a contact person name.');
            $this->back('/suppliers');
        }

        Database::execute(
            "UPDATE suppliers SET company_name=?, contact_first_name=?, contact_last_name=?, contact_number=?, address=?, notes=?, updated_at=NOW() WHERE id = ? AND store_id = ?",
            [$data['company_name'], $data['contact_first_name'], $data['contact_last_name'],
             $data['contact_number'], $data['address'], $data['notes'], $id, $storeId]
        );

        AuditService::log('update', 'suppliers', 'supplier', $id);
        Session::flash('success', 'Supplier updated.');
        $this->back('/suppliers');
    }

    /** @return array{company_name:?string,contact_first_name:?string,contact_last_name:?string,contact_number:?string,address:?string,notes:?string}|null */
    private function validated(Request $request): ?array
    {
        $companyName = $request->trimmed('company_name') ?: null;
        $contactFirst = $request->trimmed('contact_first_name') ?: null;
        $contactLast = $request->trimmed('contact_last_name') ?: null;

        if ($companyName === null && $contactFirst === null && $contactLast === null) {
            return null;
        }

        return [
            'company_name' => $companyName,
            'contact_first_name' => $contactFirst,
            'contact_last_name' => $contactLast,
            'contact_number' => $request->trimmed('contact_number') ?: null,
            'address' => $request->trimmed('address') ?: null,
            'notes' => $request->trimmed('notes') ?: null,
        ];
    }
}
