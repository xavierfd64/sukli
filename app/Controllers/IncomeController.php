<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Core\Session;
use Sukli\Services\AuditService;

class IncomeController extends Controller
{
    private const CATEGORIES = ['Sales (Non-POS)', 'Rental', 'Commission', 'Refund', 'Other'];

    public function index(Request $request): void
    {
        $storeId = Auth::storeId();
        $from = $request->trimmed('from') ?: date('Y-m-01');
        $to = $request->trimmed('to') ?: date('Y-m-d');

        $records = Database::all(
            "SELECT i.*, u.name AS created_by_name FROM income_records i
             LEFT JOIN users u ON u.id = i.created_by
             WHERE i.store_id = ? AND i.income_date BETWEEN ? AND ?
             ORDER BY i.income_date DESC, i.id DESC",
            [$storeId, $from, $to]
        );

        $total = array_sum(array_column($records, 'amount'));

        $this->view('income/index', [
            'pageTitle' => 'Income',
            'records' => $records,
            'total' => $total,
            'from' => $from,
            'to' => $to,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(Request $request): void
    {
        $storeId = Auth::storeId();
        $amount = (float) $request->input('amount', 0);
        $date = $request->trimmed('income_date') ?: date('Y-m-d');
        $category = $request->trimmed('category') ?: 'Other';

        if ($amount <= 0) {
            Session::flash('error', 'Enter a valid amount.');
            $this->back('/income');
        }

        Database::execute(
            "INSERT INTO income_records (store_id, income_date, category, amount, description, created_by) VALUES (?, ?, ?, ?, ?, ?)",
            [$storeId, $date, $category, $amount, $request->trimmed('description') ?: null, Auth::id()]
        );
        $id = (int) Database::lastInsertId();

        AuditService::log('create', 'income', 'income_record', $id, null, ['amount' => $amount, 'category' => $category]);
        Session::flash('success', 'Income recorded.');
        $this->back('/income');
    }

    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();
        $existing = Database::one("SELECT id FROM income_records WHERE id = ? AND store_id = ?", [$id, $storeId]);
        if (!$existing) {
            Session::flash('error', 'Record not found.');
            $this->back('/income');
        }

        Database::execute(
            "UPDATE income_records SET income_date=?, category=?, amount=?, description=?, updated_at=NOW() WHERE id = ? AND store_id = ?",
            [$request->trimmed('income_date'), $request->trimmed('category'), (float) $request->input('amount', 0),
             $request->trimmed('description') ?: null, $id, $storeId]
        );

        AuditService::log('update', 'income', 'income_record', $id);
        Session::flash('success', 'Income updated.');
        $this->back('/income');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();
        Database::execute("DELETE FROM income_records WHERE id = ? AND store_id = ?", [$id, $storeId]);
        AuditService::log('delete', 'income', 'income_record', $id);
        Session::flash('success', 'Income record deleted.');
        $this->back('/income');
    }
}
