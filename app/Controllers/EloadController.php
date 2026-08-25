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
use Sukli\Services\EloadProductService;
use Sukli\Services\NetworkService;
use Sukli\Services\PaymentMethodService;
use Sukli\Services\PaymentProcessor;
use Sukli\Services\UtangService;

/**
 * E-Load is a product-based sale, the same shape as a POS sale with a
 * single item: pick a customer, a network, a saved E-Load product (which
 * carries its own load value / cost / additional charge / selling price),
 * then pay. The cashier never types a peso amount — every financial field
 * on the resulting transaction is copied server-side from the selected
 * eload_products row, never trusted from the request.
 */
class EloadController extends Controller
{
    public function index(Request $request): void
    {
        $storeId = (int) Auth::storeId();
        $from = $request->trimmed('from') ?: date('Y-m-01');
        $to = $request->trimmed('to') ?: date('Y-m-d');

        $transactions = Database::all(
            "SELECT * FROM eload_transactions
             WHERE store_id = ? AND DATE(created_at) BETWEEN ? AND ?
             ORDER BY created_at DESC, id DESC",
            [$storeId, $from, $to]
        );

        $totals = [
            'selling_price' => array_sum(array_column($transactions, 'selling_price')),
            'earnings' => array_sum(array_column($transactions, 'earnings')),
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

        $products = array_map(static fn (array $p) => [
            'id' => (int) $p['id'],
            'network' => $p['network'],
            'name' => $p['name'],
            'load_value' => (float) $p['load_value'],
            'selling_price' => (float) $p['selling_price'],
        ], EloadProductService::active($storeId));

        $this->view('eload/index', [
            'pageTitle' => 'E-Load',
            'transactions' => $transactions,
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
            'customers' => $customers,
            'networks' => NetworkService::enabled($storeId),
            'eloadProducts' => $products,
            'paymentMethods' => PaymentMethodService::enabled($storeId),
        ]);
    }

    public function checkout(Request $request): void
    {
        $storeId = (int) Auth::storeId();

        $eloadProductId = (int) $request->input('eload_product_id', 0);
        $product = $eloadProductId > 0
            ? Database::one("SELECT * FROM eload_products WHERE id = ? AND store_id = ? AND is_active = 1", [$eloadProductId, $storeId])
            : null;

        if (!$product) {
            Session::flash('error', 'Select a valid, active E-Load product.');
            $this->redirect('/eload');
        }

        $paymentsRaw = json_decode((string) $request->input('payments_json', '[]'), true);
        if (!is_array($paymentsRaw)) {
            $paymentsRaw = [];
        }

        $customerId = $request->input('customer_id') !== '' ? (int) $request->input('customer_id') : null;
        $customerName = $request->trimmed('customer_name') ?: 'Walk-In';
        $contactNumber = $request->trimmed('contact_number') ?: null;

        // Every financial field comes from the saved product, never the
        // request — the cashier's browser only ever sent which product was
        // picked and how it's being paid for.
        $loadValue = (float) $product['load_value'];
        $storeCost = (float) $product['cost'];
        $additionalCharge = (float) $product['additional_charge'];
        $sellingPrice = (float) $product['selling_price'];
        $earnings = round($sellingPrice - $storeCost, 2);

        Database::beginTransaction();
        try {
            $result = PaymentProcessor::process($paymentsRaw, $sellingPrice, $storeId);

            if ($result['usesUtang'] && !$customerId) {
                throw new \RuntimeException('Select a customer for Utang E-Load sales.');
            }

            Database::execute(
                "INSERT INTO eload_transactions
                    (store_id, customer_id, customer_name, contact_number, eload_product_id, network, product_name,
                     load_value, store_cost, additional_charge, selling_price, earnings,
                     payment_method, amount_tendered, change_amount, status, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW())",
                [
                    $storeId, $customerId, $customerName, $contactNumber, $product['id'], $product['network'], $product['name'],
                    $loadValue, $storeCost, $additionalCharge, $sellingPrice, $earnings,
                    $result['paymentMethodLabel'], $result['amountTendered'], $result['changeAmount'], Auth::id(),
                ]
            );
            $transactionId = (int) Database::lastInsertId();

            foreach ($result['payments'] as $p) {
                $recordedAmount = $result['isSplit'] ? $p['amount'] : $sellingPrice;
                Database::execute(
                    "INSERT INTO eload_payments (eload_transaction_id, method, amount, created_at) VALUES (?, ?, ?, NOW())",
                    [$transactionId, $p['method'], $recordedAmount]
                );
                if ($p['method'] === 'utang') {
                    UtangService::recordSaleCredit($storeId, (int) $customerId, null, $recordedAmount, $transactionId);
                }
            }

            AuditService::log('eload_sale_completed', 'eload', 'eload_transaction', $transactionId, null, [
                'network' => $product['network'],
                'product_name' => $product['name'],
                'selling_price' => $sellingPrice,
                'earnings' => $earnings,
                'payment_method' => $result['paymentMethodLabel'],
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            Session::flash('error', $e->getMessage());
            $this->redirect('/eload');
        }

        $changeMsg = $result['changeAmount'] > 0 ? sprintf(' Change: ₱%s.', number_format($result['changeAmount'], 2)) : '';
        Session::flash('success', "E-Load sale recorded: {$product['network']} {$product['name']}.{$changeMsg}");
        $this->redirect('/eload');
    }
}
