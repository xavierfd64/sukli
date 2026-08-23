<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Core\Session;
use Sukli\Services\AuditService;

class ExpenseController extends Controller
{
    private const CATEGORIES = ['Restock / Supplies', 'Utilities', 'Rent', 'Transportation', 'Salary', 'Other'];

    public function index(Request $request): void
    {
        $storeId = Auth::storeId();
        $from = $request->trimmed('from') ?: date('Y-m-01');
        $to = $request->trimmed('to') ?: date('Y-m-d');

        $records = Database::all(
            "SELECT e.*, u.name AS created_by_name FROM expense_records e
             LEFT JOIN users u ON u.id = e.created_by
             WHERE e.store_id = ? AND e.expense_date BETWEEN ? AND ?
             ORDER BY e.expense_date DESC, e.id DESC",
            [$storeId, $from, $to]
        );

        $total = array_sum(array_column($records, 'amount'));

        $this->view('expenses/index', [
            'pageTitle' => 'Expenses',
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
        $date = $request->trimmed('expense_date') ?: date('Y-m-d');
        $category = $request->trimmed('category') ?: 'Other';

        if ($amount <= 0) {
            Session::flash('error', 'Enter a valid amount.');
            $this->back('/expenses');
        }

        Database::execute(
            "INSERT INTO expense_records (store_id, expense_date, category, amount, description, created_by) VALUES (?, ?, ?, ?, ?, ?)",
            [$storeId, $date, $category, $amount, $request->trimmed('description') ?: null, Auth::id()]
        );
        $id = (int) Database::lastInsertId();

        AuditService::log('create', 'expenses', 'expense_record', $id, null, ['amount' => $amount, 'category' => $category]);
        Session::flash('success', 'Expense recorded.');
        $this->back('/expenses');
    }

    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();
        $existing = Database::one("SELECT id FROM expense_records WHERE id = ? AND store_id = ?", [$id, $storeId]);
        if (!$existing) {
            Session::flash('error', 'Record not found.');
            $this->back('/expenses');
        }

        Database::execute(
            "UPDATE expense_records SET expense_date=?, category=?, amount=?, description=?, updated_at=NOW() WHERE id = ? AND store_id = ?",
            [$request->trimmed('expense_date'), $request->trimmed('category'), (float) $request->input('amount', 0),
             $request->trimmed('description') ?: null, $id, $storeId]
        );

        AuditService::log('update', 'expenses', 'expense_record', $id);
        Session::flash('success', 'Expense updated.');
        $this->back('/expenses');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();
        Database::execute("DELETE FROM expense_records WHERE id = ? AND store_id = ?", [$id, $storeId]);
        AuditService::log('delete', 'expenses', 'expense_record', $id);
        Session::flash('success', 'Expense record deleted.');
        $this->back('/expenses');
    }
}
