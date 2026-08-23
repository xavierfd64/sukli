<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Core\Session;
use Sukli\Services\AuditService;
use Sukli\Services\UtangService;

class UtangController extends Controller
{
    public function index(Request $request): void
    {
        $storeId = Auth::storeId();

        $customers = Database::all(
            "SELECT c.id, c.name, c.contact_number, COALESCE(cca.outstanding_balance, 0) AS outstanding_balance
             FROM customers c
             LEFT JOIN customer_credit_accounts cca ON cca.customer_id = c.id
             WHERE c.store_id = ? AND COALESCE(cca.outstanding_balance, 0) > 0
             ORDER BY outstanding_balance DESC",
            [$storeId]
        );

        $totals = Database::one(
            "SELECT COALESCE(SUM(outstanding_balance),0) AS total_outstanding, COUNT(*) AS customers_with_balance
             FROM customer_credit_accounts WHERE store_id = ? AND outstanding_balance > 0",
            [$storeId]
        );

        $this->view('utang/index', [
            'pageTitle' => 'Utang / Credit',
            'customers' => $customers,
            'totalOutstanding' => (float) $totals['total_outstanding'],
            'customersWithBalance' => (int) $totals['customers_with_balance'],
        ]);
    }

    public function show(Request $request): void
    {
        $customerId = (int) $request->param('customerId');
        $storeId = Auth::storeId();

        $customer = Database::one("SELECT * FROM customers WHERE id = ? AND store_id = ?", [$customerId, $storeId]);
        if (!$customer) {
            Session::flash('error', 'Customer not found.');
            $this->redirect('/utang');
        }

        $balance = UtangService::balance($customerId);

        $totalCredit = Database::one(
            "SELECT COALESCE(SUM(amount),0) AS total FROM utang_transactions WHERE customer_id = ?",
            [$customerId]
        );
        $totalPaid = Database::one(
            "SELECT COALESCE(SUM(amount),0) AS total FROM utang_payments WHERE customer_id = ?",
            [$customerId]
        );

        $history = Database::all(
            "(SELECT 'charge' AS kind, amount, created_at, note, sale_id FROM utang_transactions WHERE customer_id = ?)
             UNION ALL
             (SELECT 'payment' AS kind, amount, created_at, note, NULL FROM utang_payments WHERE customer_id = ?)
             ORDER BY created_at DESC LIMIT 50",
            [$customerId, $customerId]
        );

        $this->view('utang/show', [
            'pageTitle' => 'Utang — ' . $customer['name'],
            'customer' => $customer,
            'balance' => $balance,
            'totalCredit' => (float) $totalCredit['total'],
            'totalPaid' => (float) $totalPaid['total'],
            'history' => $history,
        ]);
    }

    public function recordPayment(Request $request): void
    {
        $customerId = (int) $request->param('customerId');
        $storeId = (int) Auth::storeId();

        $customer = Database::one("SELECT id FROM customers WHERE id = ? AND store_id = ?", [$customerId, $storeId]);
        if (!$customer) {
            Session::flash('error', 'Customer not found.');
            $this->redirect('/utang');
        }

        $amount = (float) $request->input('amount', 0);
        if ($amount <= 0) {
            Session::flash('error', 'Enter a valid payment amount.');
            $this->redirect('/utang/' . $customerId);
        }

        $method = in_array($request->input('payment_method'), ['cash', 'gcash'], true) ? $request->input('payment_method') : 'cash';
        $note = $request->trimmed('note') ?: null;

        UtangService::recordPayment($storeId, $customerId, $amount, $method, $note);

        AuditService::log('utang_payment', 'utang', 'customer', $customerId, null, ['amount' => $amount, 'method' => $method]);
        Session::flash('success', 'Payment recorded.');
        $this->redirect('/utang/' . $customerId);
    }
}
