<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Core\Session;
use Sukli\Services\AuditService;
use Sukli\Services\BarcodeService;
use Sukli\Services\StockService;
use Sukli\Services\UploadService;

class InventoryController extends Controller
{
    public function index(Request $request): void
    {
        $storeId = Auth::storeId();

        $search = $request->trimmed('q');
        $categoryId = $request->input('category_id', '');
        $supplierId = $request->input('supplier_id', '');
        $status = $request->input('status', '');
        $filter = $request->input('filter', '');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 15;

        $where = ['p.store_id = ?'];
        $params = [$storeId];

        if ($search !== '') {
            $where[] = '(p.name LIKE ? OR p.barcode LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($categoryId !== '') {
            $where[] = 'p.category_id = ?';
            $params[] = (int) $categoryId;
        }
        if ($supplierId !== '') {
            $where[] = 'p.supplier_id = ?';
            $params[] = (int) $supplierId;
        }
        if ($filter === 'low_stock') {
            $where[] = 'p.status = "active" AND p.current_stock > 0 AND p.current_stock <= p.min_stock';
        } elseif ($filter === 'out_of_stock') {
            $where[] = 'p.status = "active" AND p.current_stock <= 0';
        } elseif ($filter === 'expiring_soon') {
            $where[] = 'p.expiry_date IS NOT NULL AND p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND p.status = "active"';
        } elseif ($status !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        } else {
            $where[] = "p.status = 'active'";
        }

        $whereSql = implode(' AND ', $where);
        $fromSql = 'products p LEFT JOIN product_categories c ON c.id = p.category_id LEFT JOIN suppliers s ON s.id = p.supplier_id';

        $total = (int) Database::one("SELECT COUNT(*) AS cnt FROM {$fromSql} WHERE {$whereSql}", $params)['cnt'];
        $offset = ($page - 1) * $perPage;

        $products = Database::all(
            "SELECT p.*, c.name AS category_name, s.company_name AS supplier_company, s.contact_first_name AS supplier_first_name, s.contact_last_name AS supplier_last_name
             FROM {$fromSql}
             WHERE {$whereSql} ORDER BY p.name ASC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $categories = Database::all("SELECT id, name FROM product_categories WHERE store_id = ? ORDER BY name", [$storeId]);
        $suppliers = Database::all("SELECT id, company_name, contact_first_name, contact_last_name FROM suppliers WHERE store_id = ? AND status = 'active' ORDER BY company_name, contact_first_name", [$storeId]);

        $stats = Database::one(
            "SELECT COUNT(*) AS total_products,
                    COALESCE(SUM(current_stock * cost_price), 0) AS stock_value,
                    SUM(CASE WHEN current_stock > 0 AND current_stock <= min_stock THEN 1 ELSE 0 END) AS low_stock,
                    SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END) AS out_of_stock
             FROM products WHERE store_id = ? AND status = 'active'",
            [$storeId]
        );

        $this->view('inventory/index', [
            'pageTitle' => 'Inventory',
            'products' => $products,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'stats' => $stats,
            'categoryCount' => count($categories),
            'search' => $search,
            'categoryId' => $categoryId,
            'supplierId' => $supplierId,
            'filter' => $filter,
            'page' => $page,
            'totalPages' => (int) ceil($total / $perPage),
            'total' => $total,
        ]);
    }

    public function create(Request $request): void
    {
        $storeId = Auth::storeId();
        $categories = Database::all("SELECT id, name FROM product_categories WHERE store_id = ? ORDER BY name", [$storeId]);
        $suppliers = Database::all("SELECT id, company_name, contact_first_name, contact_last_name FROM suppliers WHERE store_id = ? AND status = 'active' ORDER BY company_name, contact_first_name", [$storeId]);
        $this->view('inventory/form', [
            'pageTitle' => 'Add Product',
            'product' => null,
            'categories' => $categories,
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validated($request);
        $storeId = Auth::storeId();

        if (!$data['barcode']) {
            $data['barcode'] = BarcodeService::generate((int) $storeId);
        }

        try {
            $imagePath = UploadService::store($request->file('image'), 'products/' . $storeId);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/inventory/create');
        }

        Database::beginTransaction();
        try {
            Database::execute(
                "INSERT INTO products (store_id, category_id, supplier_id, name, barcode, image_path, unit, cost_price, selling_price, current_stock, min_stock, expiry_date, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'active')",
                [$storeId, $data['category_id'], $data['supplier_id'], $data['name'], $data['barcode'], $imagePath, $data['unit'],
                 $data['cost_price'], $data['selling_price'], $data['min_stock'], $data['expiry_date']]
            );
            $productId = (int) Database::lastInsertId();

            if ($data['current_stock'] > 0) {
                StockService::record($storeId, $productId, 'initial', $data['current_stock'], null, null, 'Initial stock on product creation');
            }

            AuditService::log('create', 'inventory', 'product', $productId, null, $data);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            Session::flash('error', 'Could not save the product. ' . (Auth::hasRole(['owner']) ? $e->getMessage() : ''));
            $this->redirect('/inventory/create');
        }

        Session::flash('success', 'Product added.');
        $this->redirect('/inventory');
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->param('id');
        $product = Database::one("SELECT * FROM products WHERE id = ? AND store_id = ?", [$id, Auth::storeId()]);
        if (!$product) {
            Session::flash('error', 'Product not found.');
            $this->redirect('/inventory');
        }
        $categories = Database::all("SELECT id, name FROM product_categories WHERE store_id = ? ORDER BY name", [Auth::storeId()]);
        $suppliers = Database::all("SELECT id, company_name, contact_first_name, contact_last_name FROM suppliers WHERE store_id = ? AND status = 'active' ORDER BY company_name, contact_first_name", [Auth::storeId()]);
        $this->view('inventory/form', [
            'pageTitle' => 'Edit Product',
            'product' => $product,
            'categories' => $categories,
            'suppliers' => $suppliers,
        ]);
    }

    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();
        $existing = Database::one("SELECT * FROM products WHERE id = ? AND store_id = ?", [$id, $storeId]);
        if (!$existing) {
            Session::flash('error', 'Product not found.');
            $this->redirect('/inventory');
        }

        $data = $this->validated($request);
        if (!$data['barcode']) {
            $data['barcode'] = $existing['barcode'] ?: BarcodeService::generate((int) $storeId);
        }

        $imagePath = $existing['image_path'];
        try {
            $uploaded = UploadService::store($request->file('image'), 'products/' . $storeId);
            if ($uploaded) {
                UploadService::delete($imagePath);
                $imagePath = $uploaded;
            }
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/inventory/' . $id . '/edit');
        }

        Database::execute(
            "UPDATE products SET category_id=?, supplier_id=?, name=?, barcode=?, image_path=?, unit=?, cost_price=?, selling_price=?, min_stock=?, expiry_date=?, updated_at=NOW()
             WHERE id = ? AND store_id = ?",
            [$data['category_id'], $data['supplier_id'], $data['name'], $data['barcode'], $imagePath, $data['unit'], $data['cost_price'],
             $data['selling_price'], $data['min_stock'], $data['expiry_date'], $id, $storeId]
        );

        AuditService::log('update', 'inventory', 'product', $id, $existing, $data);
        Session::flash('success', 'Product updated.');
        $this->redirect('/inventory');
    }

    public function labels(Request $request): void
    {
        $storeId = Auth::storeId();
        $idsParam = $request->trimmed('ids');
        $ids = array_filter(array_map('intval', explode(',', $idsParam)));

        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $products = Database::all(
                "SELECT id, name, barcode, selling_price FROM products WHERE store_id = ? AND id IN ({$placeholders}) ORDER BY name",
                array_merge([$storeId], $ids)
            );
        } else {
            $products = Database::all(
                "SELECT id, name, barcode, selling_price FROM products WHERE store_id = ? AND status = 'active' ORDER BY name",
                [$storeId]
            );
        }

        $this->view('inventory/labels', [
            'pageTitle' => 'Print Barcode Labels',
            'products' => $products,
        ]);
    }

    public function archive(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();
        $product = Database::one("SELECT id, status FROM products WHERE id = ? AND store_id = ?", [$id, $storeId]);
        if ($product) {
            $newStatus = $product['status'] === 'active' ? 'archived' : 'active';
            Database::execute("UPDATE products SET status = ? WHERE id = ? AND store_id = ?", [$newStatus, $id, $storeId]);
            AuditService::log($newStatus === 'archived' ? 'archive' : 'restore', 'inventory', 'product', $id);
            Session::flash('success', $newStatus === 'archived' ? 'Product archived.' : 'Product restored.');
        }
        $this->back('/inventory');
    }

    public function adjustStock(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();
        $product = Database::one("SELECT id, current_stock FROM products WHERE id = ? AND store_id = ?", [$id, $storeId]);
        if (!$product) {
            Session::flash('error', 'Product not found.');
            $this->back('/inventory');
        }

        $direction = $request->input('direction') === 'out' ? 'out' : 'in';
        $qty = max(1, (int) $request->input('quantity', 0));
        $note = $request->trimmed('note') ?: null;

        StockService::record($storeId, $id, $direction === 'in' ? 'adjustment_in' : 'adjustment_out', $qty, null, null, $note);

        AuditService::log('stock_adjustment', 'inventory', 'product', $id, null, ['direction' => $direction, 'quantity' => $qty, 'note' => $note]);
        Session::flash('success', 'Stock adjusted.');
        $this->back('/inventory');
    }

    public function categories(Request $request): void
    {
        $storeId = Auth::storeId();
        $categories = Database::all(
            "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
             FROM product_categories c WHERE c.store_id = ? ORDER BY c.name",
            [$storeId]
        );
        $this->view('inventory/categories', [
            'pageTitle' => 'Product Categories',
            'categories' => $categories,
        ]);
    }

    public function storeCategory(Request $request): void
    {
        $name = $request->trimmed('name');
        if ($name === '') {
            Session::flash('error', 'Enter a category name.');
            $this->back('/inventory/categories');
        }

        $storeId = Auth::storeId();
        $existing = Database::one("SELECT id FROM product_categories WHERE store_id = ? AND name = ?", [$storeId, $name]);
        if ($existing) {
            Session::flash('error', 'A category with that name already exists.');
            $this->back('/inventory/categories');
        }

        Database::execute("INSERT INTO product_categories (store_id, name) VALUES (?, ?)", [$storeId, $name]);
        $id = (int) Database::lastInsertId();
        AuditService::log('create', 'inventory', 'product_category', $id, null, ['name' => $name]);
        Session::flash('success', 'Category added.');
        $this->back('/inventory/categories');
    }

    public function updateCategory(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();
        $existing = Database::one("SELECT id, name FROM product_categories WHERE id = ? AND store_id = ?", [$id, $storeId]);
        if (!$existing) {
            Session::flash('error', 'Category not found.');
            $this->back('/inventory/categories');
        }

        $name = $request->trimmed('name');
        if ($name === '') {
            Session::flash('error', 'Enter a category name.');
            $this->back('/inventory/categories');
        }

        $duplicate = Database::one("SELECT id FROM product_categories WHERE store_id = ? AND name = ? AND id != ?", [$storeId, $name, $id]);
        if ($duplicate) {
            Session::flash('error', 'A category with that name already exists.');
            $this->back('/inventory/categories');
        }

        Database::execute("UPDATE product_categories SET name = ? WHERE id = ? AND store_id = ?", [$name, $id, $storeId]);
        AuditService::log('update', 'inventory', 'product_category', $id, $existing, ['name' => $name]);
        Session::flash('success', 'Category updated.');
        $this->back('/inventory/categories');
    }

    public function deleteCategory(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();
        $existing = Database::one("SELECT id, name FROM product_categories WHERE id = ? AND store_id = ?", [$id, $storeId]);
        if (!$existing) {
            Session::flash('error', 'Category not found.');
            $this->back('/inventory/categories');
        }

        // products.category_id has ON DELETE SET NULL — affected products become Uncategorized, nothing is lost.
        Database::execute("DELETE FROM product_categories WHERE id = ? AND store_id = ?", [$id, $storeId]);
        AuditService::log('delete', 'inventory', 'product_category', $id, $existing);
        Session::flash('success', 'Category deleted.');
        $this->back('/inventory/categories');
    }

    public function exportCsv(Request $request): void
    {
        $storeId = Auth::storeId();
        $products = Database::all(
            "SELECT p.name, p.barcode, c.name AS category, p.cost_price, p.selling_price, p.current_stock, p.min_stock, p.unit, p.status
             FROM products p LEFT JOIN product_categories c ON c.id = p.category_id
             WHERE p.store_id = ? ORDER BY p.name",
            [$storeId]
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sukli-inventory-' . date('Ymd-His') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['name', 'barcode', 'category', 'cost_price', 'selling_price', 'current_stock', 'min_stock', 'unit', 'status'], ',', '"', '\\');
        foreach ($products as $row) {
            fputcsv($out, $row, ',', '"', '\\');
        }
        fclose($out);
        exit;
    }

    public function importCsv(Request $request): void
    {
        $file = $request->file('file');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Please choose a valid CSV file.');
            $this->redirect('/inventory');
        }

        $storeId = Auth::storeId();
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            Session::flash('error', 'Could not read the uploaded file.');
            $this->redirect('/inventory');
        }

        $header = fgetcsv($handle);
        $header = $header ? array_map('strtolower', array_map('trim', $header)) : [];
        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $record = array_combine($header, array_pad($row, count($header), null));
            if (empty($record['name'])) {
                continue;
            }

            $categoryId = null;
            if (!empty($record['category'])) {
                Database::execute(
                    "INSERT INTO product_categories (store_id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)",
                    [$storeId, trim($record['category'])]
                );
                $cat = Database::one("SELECT id FROM product_categories WHERE store_id = ? AND name = ?", [$storeId, trim($record['category'])]);
                $categoryId = $cat['id'] ?? null;
            }

            $barcode = trim((string) ($record['barcode'] ?? '')) ?: null;
            $existing = $barcode ? Database::one("SELECT id FROM products WHERE store_id = ? AND barcode = ?", [$storeId, $barcode]) : null;

            $costPrice = (float) ($record['cost_price'] ?? 0);
            $sellingPrice = (float) ($record['selling_price'] ?? 0);
            $minStock = max(0, (int) ($record['min_stock'] ?? 0));
            $unit = trim((string) ($record['unit'] ?? '')) ?: 'pc';

            if ($existing) {
                Database::execute(
                    "UPDATE products SET category_id=?, name=?, cost_price=?, selling_price=?, min_stock=?, unit=?, updated_at=NOW() WHERE id=?",
                    [$categoryId, trim($record['name']), $costPrice, $sellingPrice, $minStock, $unit, $existing['id']]
                );
            } else {
                $stock = max(0, (int) ($record['current_stock'] ?? 0));
                Database::execute(
                    "INSERT INTO products (store_id, category_id, name, barcode, unit, cost_price, selling_price, current_stock, min_stock, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, 'active')",
                    [$storeId, $categoryId, trim($record['name']), $barcode, $unit, $costPrice, $sellingPrice, $minStock]
                );
                $newId = (int) Database::lastInsertId();
                if ($stock > 0) {
                    StockService::record($storeId, $newId, 'initial', $stock, null, null, 'Imported via CSV');
                }
            }
            $imported++;
        }
        fclose($handle);

        AuditService::log('import', 'inventory', 'product', null, null, ['rows' => $imported]);
        Session::flash('success', "Imported {$imported} product(s).");
        $this->redirect('/inventory');
    }

    private function validated(Request $request): array
    {
        return [
            'category_id' => $request->input('category_id') !== '' ? (int) $request->input('category_id') : null,
            'supplier_id' => $request->input('supplier_id') !== '' ? (int) $request->input('supplier_id') : null,
            'name' => $request->trimmed('name'),
            'barcode' => $request->trimmed('barcode') ?: null,
            'unit' => $request->trimmed('unit') ?: 'pc',
            'cost_price' => (float) $request->input('cost_price', 0),
            'selling_price' => (float) $request->input('selling_price', 0),
            'current_stock' => max(0, (int) $request->input('current_stock', 0)),
            'min_stock' => max(0, (int) $request->input('min_stock', 0)),
            'expiry_date' => $request->trimmed('expiry_date') ?: null,
        ];
    }
}
