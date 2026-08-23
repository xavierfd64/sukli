<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Core\Session;
use Sukli\Services\AuditService;

class GcashController extends Controller
{
    public function index(Request $request): void
    {
        $storeId = Auth::storeId();
        $from = $request->trimmed('from') ?: date('Y-m-01');
        $to = $request->trimmed('to') ?: date('Y-m-d');

        $records = Database::all(
            "SELECT * FROM gcash_records WHERE store_id = ? AND DATE(transacted_at) BETWEEN ? AND ?
             ORDER BY transacted_at DESC, id DESC",
            [$storeId, $from, $to]
        );

        $cashIn = array_sum(array_map(fn ($r) => $r['type'] === 'cash_in' ? (float) $r['amount'] : 0, $records));
        $cashOut = array_sum(array_map(fn ($r) => $r['type'] === 'cash_out' ? (float) $r['amount'] : 0, $records));
        $serviceCharges = array_sum(array_column($records, 'service_charge'));

        $this->view('gcash/index', [
            'pageTitle' => 'GCash Cash-In / Cash-Out',
            'records' => $records,
            'cashIn' => $cashIn,
            'cashOut' => $cashOut,
            'serviceCharges' => $serviceCharges,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function store(Request $request): void
    {
        $storeId = Auth::storeId();
        $type = $request->input('type') === 'cash_out' ? 'cash_out' : 'cash_in';
        $amount = (float) $request->input('amount', 0);
        $serviceCharge = (float) $request->input('service_charge', 0);

        if ($amount <= 0) {
            Session::flash('error', 'Enter a valid amount.');
            $this->back('/gcash');
        }

        Database::execute(
            "INSERT INTO gcash_records (store_id, transacted_at, type, amount, service_charge, customer_reference, notes, created_by)
             VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)",
            [$storeId, $type, $amount, $serviceCharge, $request->trimmed('customer_reference') ?: null,
             $request->trimmed('notes') ?: null, Auth::id()]
        );
        $id = (int) Database::lastInsertId();

        AuditService::log('create', 'gcash', 'gcash_record', $id, null, ['type' => $type, 'amount' => $amount]);
        Session::flash('success', 'GCash transaction recorded.');
        $this->back('/gcash');
    }
}
