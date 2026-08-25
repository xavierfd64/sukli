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
use Sukli\Services\NetworkService;

class EloadController extends Controller
{
    public function index(Request $request): void
    {
        $storeId = Auth::storeId();
        $from = $request->trimmed('from') ?: date('Y-m-01');
        $to = $request->trimmed('to') ?: date('Y-m-d');

        $records = Database::all(
            "SELECT * FROM eload_records WHERE store_id = ? AND DATE(transacted_at) BETWEEN ? AND ?
             ORDER BY transacted_at DESC, id DESC",
            [$storeId, $from, $to]
        );

        $totals = [
            'load_amount' => array_sum(array_column($records, 'load_amount')),
            'amount_received' => array_sum(array_column($records, 'amount_received')),
            'profit' => array_sum(array_column($records, 'profit')),
        ];

        $customerRows = Database::all(
            "SELECT id, first_name, last_name, contact_number FROM customers WHERE store_id = ? AND status = 'active' ORDER BY first_name, last_name",
            [$storeId]
        );
        $customers = array_map(static fn (array $c) => [
            'id' => (int) $c['id'],
            'name' => CustomerSearchService::fullName($c),
            'contact_number' => $c['contact_number'],
        ], $customerRows);

        $this->view('eload/index', [
            'pageTitle' => 'E-Load Records',
            'records' => $records,
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
            'customers' => $customers,
            'networks' => NetworkService::enabled((int) $storeId),
        ]);
    }

    public function store(Request $request): void
    {
        $storeId = Auth::storeId();
        $loadAmount = (float) $request->input('load_amount', 0);
        $amountReceived = (float) $request->input('amount_received', 0);
        $cost = (float) $request->input('cost', 0);

        if ($loadAmount <= 0 || $amountReceived <= 0) {
            Session::flash('error', 'Enter valid load and received amounts.');
            $this->back('/eload');
        }

        $profit = round($amountReceived - $cost, 2);
        $customerName = $request->trimmed('customer_name') ?: 'Walk-In';

        Database::execute(
            "INSERT INTO eload_records (store_id, transacted_at, customer_name, mobile_number, network, load_amount, amount_received, cost, profit, notes, created_by)
             VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$storeId, $customerName, $request->trimmed('mobile_number') ?: null,
             $request->trimmed('network') ?: null, $loadAmount, $amountReceived, $cost, $profit,
             $request->trimmed('notes') ?: null, Auth::id()]
        );
        $id = (int) Database::lastInsertId();

        AuditService::log('create', 'eload', 'eload_record', $id, null, ['load_amount' => $loadAmount, 'profit' => $profit]);
        Session::flash('success', 'E-Load transaction recorded.');
        $this->back('/eload');
    }
}
