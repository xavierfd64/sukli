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
use Sukli\Services\PaymentMethodService;
use Sukli\Services\StockService;
use Sukli\Services\SystemSettingsService;
use Sukli\Services\UtangService;

class PosController extends Controller
{
    public function index(Request $request): void
    {
        $storeId = Auth::storeId();

        $products = Database::all(
            "SELECT p.id, p.name, p.selling_price, p.current_stock, p.unit, p.barcode, c.name AS category_name
             FROM products p LEFT JOIN product_categories c ON c.id = p.category_id
             WHERE p.store_id = ? AND p.status = 'active' AND p.current_stock > 0
             ORDER BY p.name ASC",
            [$storeId]
        );

        $categories = [];
        foreach ($products as $p) {
            $cat = $p['category_name'] ?: 'Others';
            $categories[$cat] = true;
        }

        $customerRows = Database::all(
            "SELECT id, first_name, last_name, contact_number FROM customers WHERE store_id = ? AND status = 'active' ORDER BY first_name, last_name",
            [$storeId]
        );
        $customers = array_map(static fn (array $c) => [
            'id' => (int) $c['id'],
            'name' => CustomerSearchService::fullName($c),
            'contact_number' => $c['contact_number'],
        ], $customerRows);

        $this->view('pos/index', [
            'pageTitle' => 'POS',
            'products' => $products,
            'categories' => array_keys($categories),
            'customers' => $customers,
            'paymentMethods' => PaymentMethodService::enabled((int) $storeId),
        ]);
    }

    public function checkout(Request $request): void
    {
        $storeId = (int) Auth::storeId();
        $cartRaw = $request->input('cart_json', '[]');
        $cart = json_decode((string) $cartRaw, true);

        if (!is_array($cart) || count($cart) === 0) {
            Session::flash('error', 'Your cart is empty.');
            $this->redirect('/pos');
        }

        $paymentsRaw = json_decode((string) $request->input('payments_json', '[]'), true);
        if (!is_array($paymentsRaw) || count($paymentsRaw) === 0) {
            Session::flash('error', 'Please select a payment method.');
            $this->redirect('/pos');
        }

        $enabledMethods = PaymentMethodService::enabled($storeId);
        $isSplit = count($paymentsRaw) > 1;
        $payments = [];
        $paymentsSum = 0.0;
        $usesUtang = false;

        foreach ($paymentsRaw as $row) {
            $method = (string) ($row['method'] ?? '');
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if (!isset($enabledMethods[$method])) {
                Session::flash('error', 'One of the selected payment methods is not available.');
                $this->redirect('/pos');
            }
            if ($amount <= 0) {
                Session::flash('error', 'Payment amounts must be greater than zero.');
                $this->redirect('/pos');
            }
            if ($method === 'utang') {
                $usesUtang = true;
            }
            $payments[] = ['method' => $method, 'amount' => $amount];
            $paymentsSum += $amount;
        }

        $customerId = $request->input('customer_id') !== '' ? (int) $request->input('customer_id') : null;
        if ($usesUtang && !$customerId) {
            Session::flash('error', 'Select a customer for Utang sales.');
            $this->redirect('/pos');
        }

        $discountPercent = max(0, min(100, (float) $request->input('discount_percent', 0)));

        Database::beginTransaction();
        try {
            $subtotal = 0.0;
            $lineItems = [];

            foreach ($cart as $item) {
                $productId = (int) ($item['id'] ?? 0);
                $qty = max(1, (int) ($item['qty'] ?? 0));

                $product = Database::one(
                    "SELECT id, name, selling_price, current_stock FROM products WHERE id = ? AND store_id = ? AND status = 'active' FOR UPDATE",
                    [$productId, $storeId]
                );
                if (!$product) {
                    throw new \RuntimeException('One of the items is no longer available.');
                }
                if ($product['current_stock'] < $qty) {
                    throw new \RuntimeException("Not enough stock for {$product['name']}. Only {$product['current_stock']} left.");
                }

                $lineTotal = round((float) $product['selling_price'] * $qty, 2);
                $subtotal += $lineTotal;
                $lineItems[] = [
                    'product_id' => $product['id'],
                    'name' => $product['name'],
                    'unit_price' => (float) $product['selling_price'],
                    'qty' => $qty,
                    'line_total' => $lineTotal,
                ];
            }

            $discountAmount = round($subtotal * ($discountPercent / 100), 2);
            $total = max(0, $subtotal - $discountAmount);

            $changeAmount = 0.0;
            if ($isSplit) {
                if (abs($paymentsSum - $total) > 0.01) {
                    throw new \RuntimeException('Split payment amounts must add up to the total.');
                }
            } else {
                $only = $payments[0];
                if ($only['method'] === 'cash') {
                    if ($only['amount'] < $total) {
                        throw new \RuntimeException('Cash received is less than the total.');
                    }
                    $changeAmount = round($only['amount'] - $total, 2);
                } elseif ($paymentsSum + 0.01 < $total) {
                    throw new \RuntimeException('Payment amount is less than the total.');
                }
            }

            $paymentMethodLabel = $isSplit ? 'split' : $payments[0]['method'];
            $amountTendered = $isSplit ? $paymentsSum : ($payments[0]['method'] === 'cash' ? $payments[0]['amount'] : $total);

            Database::execute(
                "INSERT INTO sales (store_id, sale_number, customer_id, cashier_id, subtotal, discount_amount, total, payment_method, amount_tendered, change_amount, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())",
                [$storeId, 'TEMP', $customerId, Auth::id(), $subtotal, $discountAmount, $total, $paymentMethodLabel, $amountTendered, $changeAmount]
            );
            $saleId = (int) Database::lastInsertId();
            $saleNumber = str_pad((string) $saleId, 6, '0', STR_PAD_LEFT);
            Database::execute("UPDATE sales SET sale_number = ? WHERE id = ?", [$saleNumber, $saleId]);

            foreach ($lineItems as $li) {
                Database::execute(
                    "INSERT INTO sale_items (sale_id, product_id, product_name, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?)",
                    [$saleId, $li['product_id'], $li['name'], $li['unit_price'], $li['qty'], $li['line_total']]
                );
                StockService::record($storeId, $li['product_id'], 'sale_out', $li['qty'], 'sale', $saleId, "Sale #{$saleNumber}");
            }

            foreach ($payments as $p) {
                $recordedAmount = $isSplit ? $p['amount'] : $total;
                Database::execute(
                    "INSERT INTO payments (sale_id, method, amount, created_at) VALUES (?, ?, ?, NOW())",
                    [$saleId, $p['method'], $recordedAmount]
                );
                if ($p['method'] === 'utang') {
                    UtangService::recordSaleCredit($storeId, (int) $customerId, $saleId, $recordedAmount);
                }
            }

            AuditService::log('sale_completed', 'pos', 'sale', $saleId, null, [
                'sale_number' => $saleNumber,
                'total' => $total,
                'payment_method' => $paymentMethodLabel,
                'payments' => $payments,
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            Session::flash('error', $e->getMessage());
            $this->redirect('/pos');
        }

        $this->redirect('/pos/receipt/' . $saleId);
    }

    public function receipt(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();

        $sale = Database::one(
            "SELECT s.*, u.name AS cashier_name, c.first_name AS customer_first_name, c.last_name AS customer_last_name
             FROM sales s
             JOIN users u ON u.id = s.cashier_id
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE s.id = ? AND s.store_id = ?",
            [$id, $storeId]
        );
        if (!$sale) {
            Session::flash('error', 'Receipt not found.');
            $this->redirect('/pos');
        }

        $items = Database::all("SELECT * FROM sale_items WHERE sale_id = ?", [$id]);
        $payments = Database::all("SELECT * FROM payments WHERE sale_id = ? ORDER BY id", [$id]);
        $store = Database::one("SELECT * FROM stores WHERE id = ?", [$storeId]);

        $this->view('pos/receipt', [
            'pageTitle' => 'Receipt #' . $sale['sale_number'],
            'sale' => $sale,
            'items' => $items,
            'payments' => $payments,
            'store' => $store,
            'autoPrintReceipt' => SystemSettingsService::getBool((int) $storeId, 'auto_print_receipt'),
        ]);
    }
}
