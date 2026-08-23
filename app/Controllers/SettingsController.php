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

class SettingsController extends Controller
{
    public function index(Request $request): void
    {
        $storeId = Auth::storeId();
        $store = Database::one("SELECT * FROM stores WHERE id = ?", [$storeId]);
        $categories = Database::all("SELECT id, name FROM product_categories WHERE store_id = ? ORDER BY name", [$storeId]);

        $summary = Database::one(
            "SELECT
                (SELECT COALESCE(SUM(total),0) FROM sales WHERE store_id = ? AND status='completed' AND DATE(created_at) = CURDATE()) AS sales_today,
                (SELECT COALESCE(SUM(amount),0) FROM income_records WHERE store_id = ? AND income_date = CURDATE()) AS income_today,
                (SELECT COALESCE(SUM(amount),0) FROM expense_records WHERE store_id = ? AND expense_date = CURDATE()) AS expense_today",
            [$storeId, $storeId, $storeId]
        );

        $this->view('settings/index', [
            'pageTitle' => 'Settings',
            'store' => $store,
            'categories' => $categories,
            'summary' => $summary,
            'phpVersion' => PHP_VERSION,
        ]);
    }

    public function updateGeneral(Request $request): void
    {
        $storeId = Auth::storeId();
        $existing = Database::one("SELECT * FROM stores WHERE id = ?", [$storeId]);

        Database::execute(
            "UPDATE stores SET name=?, address=?, phone=?, currency_symbol=?, tax_rate=?, receipt_footer=?, updated_at=NOW() WHERE id = ?",
            [
                $request->trimmed('name'), $request->trimmed('address') ?: null, $request->trimmed('phone') ?: null,
                $request->trimmed('currency_symbol') ?: '₱', (float) $request->input('tax_rate', 0),
                $request->trimmed('receipt_footer') ?: null, $storeId,
            ]
        );

        AuditService::log('update', 'settings', 'store', $storeId, $existing, $request->only(['name', 'address', 'phone', 'currency_symbol', 'tax_rate', 'receipt_footer']));
        Session::flash('success', 'Store settings updated.');
        $this->redirect('/settings');
    }

    public function features(Request $request): void
    {
        $storeId = (int) Auth::storeId();
        $this->view('settings/features', [
            'pageTitle' => 'Feature Management',
            'features' => FeatureService::all($storeId),
        ]);
    }

    public function updateFeatures(Request $request): void
    {
        $storeId = (int) Auth::storeId();
        $features = $request->input('features', []);
        if (!is_array($features)) {
            $features = [];
        }

        foreach (FeatureService::KEYS as $key) {
            $row = $features[$key] ?? [];
            $enabled = ($row['enabled'] ?? '') === '1';
            $showNav = ($row['show_in_nav'] ?? '') === '1';
            $showDash = ($row['show_in_dashboard'] ?? '') === '1';
            FeatureService::update($storeId, $key, $enabled, $showNav, $showDash);
        }

        AuditService::log('update', 'settings', 'feature_settings', $storeId, null, $features);
        Session::flash('success', 'Feature Management settings saved.');
        $this->redirect('/settings/features');
    }

    public function updateSecurity(Request $request): void
    {
        $userId = Auth::id();
        $current = (string) $request->input('current_password', '');
        $new = (string) $request->input('new_password', '');
        $confirm = (string) $request->input('confirm_password', '');

        $user = Database::one("SELECT password_hash FROM users WHERE id = ?", [$userId]);

        if (!$user || !password_verify($current, $user['password_hash'])) {
            Session::flash('error', 'Current password is incorrect.');
            $this->redirect('/settings');
        }
        if (strlen($new) < 8) {
            Session::flash('error', 'New password must be at least 8 characters.');
            $this->redirect('/settings');
        }
        if ($new !== $confirm) {
            Session::flash('error', 'New password and confirmation do not match.');
            $this->redirect('/settings');
        }

        Database::execute("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?", [password_hash($new, PASSWORD_DEFAULT), $userId]);
        AuditService::log('password_change', 'settings', 'user', $userId);
        Session::flash('success', 'Password updated.');
        $this->redirect('/settings');
    }

    public function downloadBackup(Request $request): void
    {
        $storeId = (int) Auth::storeId();

        // Most tables carry store_id directly; a few need a different scoping query.
        $tableQueries = [
            'stores' => 'SELECT * FROM stores WHERE id = ?',
            'users' => 'SELECT * FROM users WHERE store_id = ?',
            'product_categories' => 'SELECT * FROM product_categories WHERE store_id = ?',
            'products' => 'SELECT * FROM products WHERE store_id = ?',
            'inventory_transactions' => 'SELECT * FROM inventory_transactions WHERE store_id = ?',
            'customers' => 'SELECT * FROM customers WHERE store_id = ?',
            'customer_credit_accounts' => 'SELECT * FROM customer_credit_accounts WHERE store_id = ?',
            'sales' => 'SELECT * FROM sales WHERE store_id = ?',
            'sale_items' => 'SELECT si.* FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.store_id = ?',
            'payments' => 'SELECT p.* FROM payments p JOIN sales s ON s.id = p.sale_id WHERE s.store_id = ?',
            'utang_transactions' => 'SELECT * FROM utang_transactions WHERE store_id = ?',
            'utang_payments' => 'SELECT * FROM utang_payments WHERE store_id = ?',
            'income_records' => 'SELECT * FROM income_records WHERE store_id = ?',
            'expense_records' => 'SELECT * FROM expense_records WHERE store_id = ?',
            'eload_records' => 'SELECT * FROM eload_records WHERE store_id = ?',
            'gcash_records' => 'SELECT * FROM gcash_records WHERE store_id = ?',
            'suppliers' => 'SELECT * FROM suppliers WHERE store_id = ?',
            'feature_settings' => 'SELECT * FROM feature_settings WHERE store_id = ?',
            'system_settings' => 'SELECT * FROM system_settings WHERE store_id = ?',
        ];

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="sukli-backup-' . date('Ymd-His') . '.sql"');

        echo "-- Sukli data backup for store #{$storeId} — generated " . date('c') . "\n";
        echo "-- Data only (INSERT statements). Import via phpMyAdmin or the mysql CLI against a store with the same schema.\n\n";
        echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tableQueries as $table => $sql) {
            $rows = Database::all($sql, [$storeId]);
            if (!$rows) {
                continue;
            }
            echo "-- {$table} (" . count($rows) . " rows)\n";
            $columns = array_keys($rows[0]);
            $columnList = '`' . implode('`, `', $columns) . '`';
            foreach ($rows as $row) {
                $values = array_map(function ($v) {
                    if ($v === null) return 'NULL';
                    return Database::connection()->quote((string) $v);
                }, $row);
                echo "INSERT INTO `{$table}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n";
            }
            echo "\n";
        }

        echo "SET FOREIGN_KEY_CHECKS=1;\n";
        exit;
    }
}
