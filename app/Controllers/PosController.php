<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Core\Session;
use Sukli\Services\AuditService;
use Sukli\Services\FeatureService;
use Sukli\Services\StockService;
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

        $customers = Database::all(
            "SELECT id, name FROM customers WHERE store_id = ? AND status = 'active' ORDER BY name",
            [$storeId]
        );

        $this->view('pos/index', [
            'pageTitle' => 'POS',
            'products' => $products,
            'categories' => array_keys($categories),
            'customers' => $customers,
            'features' => FeatureService::all((int) $storeId),
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

        $paymentMethod = $request->input('payment_method', '');
        if (!in_array($paymentMethod, ['cash', 'gcash', 'utang'], true)) {
            Session::flash('error', 'Please select a payment method.');
            $this->redirect('/pos');
        }
        if ($paymentMethod !== 'cash' && !FeatureService::isEnabled($storeId, $paymentMethod)) {
            Session::flash('error', 'That payment method is currently disabled.');
            $this->redirect('/pos');
        }

        $customerId = $request->input('customer_id') !== '' ? (int) $request->input('customer_id') : null;
        if ($paymentMethod === 'utang' && !$customerId) {
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

            $amountTendered = null;
            $changeAmount = null;
            if ($paymentMethod === 'cash') {
                $amountTendered = (float) $request->input('amount_tendered', $total);
                if ($amountTendered < $total) {
                    throw new \RuntimeException('Cash received is less than the total.');
                }
                $changeAmount = round($amountTendered - $total, 2);
            } elseif ($paymentMethod === 'gcash') {
                $amountTendered = $total;
                $changeAmount = 0;
            }

            Database::execute(
                "INSERT INTO sales (store_id, sale_number, customer_id, cashier_id, subtotal, discount_amount, total, payment_method, amount_tendered, change_amount, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())",
                [$storeId, 'TEMP', $customerId, Auth::id(), $subtotal, $discountAmount, $total, $paymentMethod, $amountTendered, $changeAmount]
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

            Database::execute(
                "INSERT INTO payments (sale_id, method, amount, created_at) VALUES (?, ?, ?, NOW())",
                [$saleId, $paymentMethod, $total]
            );

            if ($paymentMethod === 'utang') {
                UtangService::recordSaleCredit($storeId, (int) $customerId, $saleId, $total);
            }

            AuditService::log('sale_completed', 'pos', 'sale', $saleId, null, [
                'sale_number' => $saleNumber, 'total' => $total, 'payment_method' => $paymentMethod,
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
            "SELECT s.*, u.name AS cashier_name, c.name AS customer_name
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
        $store = Database::one("SELECT * FROM stores WHERE id = ?", [$storeId]);

        $this->view('pos/receipt', [
            'pageTitle' => 'Receipt #' . $sale['sale_number'],
            'sale' => $sale,
            'items' => $items,
            'store' => $store,
        ]);
    }
}
