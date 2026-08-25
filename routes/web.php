<?php

declare(strict_types=1);

/** @var Sukli\Core\Router $router */

use Sukli\Controllers\AuditController;
use Sukli\Controllers\AuthController;
use Sukli\Controllers\CustomerController;
use Sukli\Controllers\DashboardController;
use Sukli\Controllers\EloadController;
use Sukli\Controllers\ExpenseController;
use Sukli\Controllers\GcashController;
use Sukli\Controllers\IncomeController;
use Sukli\Controllers\InstallController;
use Sukli\Controllers\InventoryController;
use Sukli\Controllers\PosController;
use Sukli\Controllers\ReportController;
use Sukli\Controllers\RoleController;
use Sukli\Controllers\SettingsController;
use Sukli\Controllers\SupplierController;
use Sukli\Controllers\UserController;
use Sukli\Controllers\UtangController;
use Sukli\Middleware\AuthMiddleware;
use Sukli\Middleware\CsrfMiddleware;
use Sukli\Middleware\FeatureMiddleware;
use Sukli\Middleware\GuestMiddleware;
use Sukli\Middleware\PermissionMiddleware;

$auth = AuthMiddleware::handle();
$guest = GuestMiddleware::handle();
$csrf = CsrfMiddleware::handle();
$eloadOn = FeatureMiddleware::require('eload');
$gcashOn = FeatureMiddleware::require('gcash');
$utangOn = FeatureMiddleware::require('utang');

/** Shorthand for PermissionMiddleware::requires() — granular module/action checks (see database/seed.sql for the default matrix). */
$perm = static fn (string $module, string $action) => PermissionMiddleware::requires($module, $action);

// -- Installer (public, self-contained — see app/Controllers/InstallController) --
$router->get('/install', [InstallController::class, 'welcome']);
$router->get('/install/database', [InstallController::class, 'showDatabase']);
$router->post('/install/database', [InstallController::class, 'saveDatabase'], [$csrf]);
$router->get('/install/admin', [InstallController::class, 'showAdmin']);
$router->post('/install/admin', [InstallController::class, 'saveAdmin'], [$csrf]);
$router->get('/install/store', [InstallController::class, 'showStore']);
$router->post('/install/store', [InstallController::class, 'saveStore'], [$csrf]);
$router->get('/install/finish', [InstallController::class, 'finish']);
$router->post('/install/api/test-connection', [InstallController::class, 'apiTestConnection'], [$csrf]);
$router->post('/install/api/check-requirements', [InstallController::class, 'apiCheckRequirements'], [$csrf]);
$router->post('/install/api/connect', [InstallController::class, 'apiConnect'], [$csrf]);
$router->post('/install/api/create-tables', [InstallController::class, 'apiCreateTables'], [$csrf]);
$router->post('/install/api/create-admin', [InstallController::class, 'apiCreateAdmin'], [$csrf]);
$router->post('/install/api/store-settings', [InstallController::class, 'apiStoreSettings'], [$csrf]);
$router->post('/install/api/finalize', [InstallController::class, 'apiFinalize'], [$csrf]);

// -- Auth ---------------------------------------------------------------
$router->get('/', [DashboardController::class, 'index'], [$auth]);
$router->get('/login', [AuthController::class, 'showLogin'], [$guest]);
$router->post('/login', [AuthController::class, 'login'], [$guest, $csrf]);
$router->post('/logout', [AuthController::class, 'logout'], [$auth, $csrf]);

// -- Dashboard ------------------------------------------------------------
$router->get('/dashboard', [DashboardController::class, 'index'], [$auth]);

// -- POS --------------------------------------------------------------------
$router->get('/pos', [PosController::class, 'index'], [$auth, $perm('pos', 'view')]);
$router->post('/pos/checkout', [PosController::class, 'checkout'], [$auth, $perm('pos', 'create_sale'), $csrf]);
$router->get('/pos/receipt/{id}', [PosController::class, 'receipt'], [$auth, $perm('pos', 'view')]);

// -- Inventory --------------------------------------------------------------
$router->get('/inventory', [InventoryController::class, 'index'], [$auth, $perm('inventory', 'view')]);
$router->get('/inventory/create', [InventoryController::class, 'create'], [$auth, $perm('inventory', 'add')]);
$router->post('/inventory', [InventoryController::class, 'store'], [$auth, $perm('inventory', 'add'), $csrf]);
$router->get('/inventory/labels', [InventoryController::class, 'labels'], [$auth, $perm('inventory', 'edit')]);
$router->post('/inventory/labels/print', [InventoryController::class, 'generateLabels'], [$auth, $perm('inventory', 'edit'), $csrf]);
$router->get('/inventory/export.csv', [InventoryController::class, 'exportCsv'], [$auth, $perm('inventory', 'edit')]);
$router->get('/inventory/categories', [InventoryController::class, 'categories'], [$auth, $perm('inventory', 'edit')]);
$router->post('/inventory/categories', [InventoryController::class, 'storeCategory'], [$auth, $perm('inventory', 'edit'), $csrf]);
$router->post('/inventory/categories/{id}', [InventoryController::class, 'updateCategory'], [$auth, $perm('inventory', 'edit'), $csrf]);
$router->post('/inventory/categories/{id}/delete', [InventoryController::class, 'deleteCategory'], [$auth, $perm('inventory', 'edit'), $csrf]);
$router->post('/inventory/import', [InventoryController::class, 'importCsv'], [$auth, $perm('inventory', 'edit'), $csrf]);
$router->get('/inventory/{id}/edit', [InventoryController::class, 'edit'], [$auth, $perm('inventory', 'edit')]);
$router->post('/inventory/{id}', [InventoryController::class, 'update'], [$auth, $perm('inventory', 'edit'), $csrf]);
$router->post('/inventory/{id}/archive', [InventoryController::class, 'archive'], [$auth, $perm('inventory', 'delete'), $csrf]);
$router->post('/inventory/{id}/adjust', [InventoryController::class, 'adjustStock'], [$auth, $perm('inventory', 'edit'), $csrf]);

// -- Income / Expenses --------------------------------------------------------
$router->get('/income', [IncomeController::class, 'index'], [$auth, $perm('income', 'view')]);

$router->get('/expenses', [ExpenseController::class, 'index'], [$auth, $perm('expenses', 'view')]);
$router->post('/expenses', [ExpenseController::class, 'store'], [$auth, $perm('expenses', 'add'), $csrf]);
$router->post('/expenses/{id}', [ExpenseController::class, 'update'], [$auth, $perm('expenses', 'edit'), $csrf]);
$router->post('/expenses/{id}/delete', [ExpenseController::class, 'destroy'], [$auth, $perm('expenses', 'delete'), $csrf]);

// -- E-Load / GCash (feature-flagged, recording only) ------------------------
$router->get('/eload', [EloadController::class, 'index'], [$auth, $eloadOn, $perm('eload', 'view')]);
$router->post('/eload', [EloadController::class, 'store'], [$auth, $eloadOn, $perm('eload', 'add'), $csrf]);

$router->get('/gcash', [GcashController::class, 'index'], [$auth, $gcashOn, $perm('gcash', 'view')]);
$router->post('/gcash', [GcashController::class, 'store'], [$auth, $gcashOn, $perm('gcash', 'add'), $csrf]);

// -- Utang / Credit (feature-flagged) -----------------------------------------
$router->get('/utang', [UtangController::class, 'index'], [$auth, $utangOn, $perm('utang', 'view')]);
$router->get('/utang/{customerId}', [UtangController::class, 'show'], [$auth, $utangOn, $perm('utang', 'view')]);
$router->post('/utang/{customerId}/payment', [UtangController::class, 'recordPayment'], [$auth, $utangOn, $perm('utang', 'record_payment'), $csrf]);

// -- Customers / Suppliers ----------------------------------------------------
$router->get('/customers', [CustomerController::class, 'index'], [$auth, $perm('customers', 'view')]);
$router->get('/customers/search', [CustomerController::class, 'search'], [$auth, $perm('customers', 'view')]);
$router->get('/customers/export.csv', [CustomerController::class, 'exportCsv'], [$auth, $perm('customers', 'view')]);
$router->post('/customers', [CustomerController::class, 'store'], [$auth, $perm('customers', 'add'), $csrf]);
$router->post('/customers/{id}', [CustomerController::class, 'update'], [$auth, $perm('customers', 'edit'), $csrf]);

$router->get('/suppliers', [SupplierController::class, 'index'], [$auth, $perm('suppliers', 'view')]);
$router->post('/suppliers', [SupplierController::class, 'store'], [$auth, $perm('suppliers', 'add'), $csrf]);
$router->post('/suppliers/{id}', [SupplierController::class, 'update'], [$auth, $perm('suppliers', 'edit'), $csrf]);

// -- Users & Roles ------------------------------------------------------------
$router->get('/users', [UserController::class, 'index'], [$auth, $perm('users', 'manage')]);
$router->post('/users', [UserController::class, 'store'], [$auth, $perm('users', 'manage'), $csrf]);
$router->post('/users/{id}', [UserController::class, 'update'], [$auth, $perm('users', 'manage'), $csrf]);
$router->post('/users/{id}/deactivate', [UserController::class, 'deactivate'], [$auth, $perm('users', 'manage'), $csrf]);

$router->get('/roles', [RoleController::class, 'index'], [$auth, $perm('users', 'manage')]);
$router->get('/roles/create', [RoleController::class, 'create'], [$auth, $perm('users', 'manage')]);
$router->post('/roles', [RoleController::class, 'store'], [$auth, $perm('users', 'manage'), $csrf]);
$router->get('/roles/{id}/edit', [RoleController::class, 'edit'], [$auth, $perm('users', 'manage')]);
$router->post('/roles/{id}', [RoleController::class, 'update'], [$auth, $perm('users', 'manage'), $csrf]);
$router->post('/roles/{id}/delete', [RoleController::class, 'destroy'], [$auth, $perm('users', 'manage'), $csrf]);

// -- Reports -----------------------------------------------------------------
$router->get('/reports', [ReportController::class, 'index'], [$auth, $perm('reports', 'view')]);
$router->get('/reports/export.csv', [ReportController::class, 'exportCsv'], [$auth, $perm('reports', 'view')]);

// -- Settings / Feature Management --------------------------------------------
$router->get('/settings', [SettingsController::class, 'index'], [$auth, $perm('settings', 'manage')]);
$router->post('/settings/general', [SettingsController::class, 'updateGeneral'], [$auth, $perm('settings', 'manage'), $csrf]);
$router->post('/settings/receipt', [SettingsController::class, 'updateReceipt'], [$auth, $perm('settings', 'manage'), $csrf]);
$router->post('/settings/system', [SettingsController::class, 'updateSystem'], [$auth, $perm('settings', 'manage'), $csrf]);
$router->get('/settings/features', [SettingsController::class, 'features'], [$auth, $perm('settings', 'manage')]);
$router->post('/settings/features', [SettingsController::class, 'updateFeatures'], [$auth, $perm('settings', 'manage'), $csrf]);
$router->get('/settings/payment-methods', [SettingsController::class, 'paymentMethods'], [$auth, $perm('settings', 'manage')]);
$router->post('/settings/payment-methods', [SettingsController::class, 'updatePaymentMethods'], [$auth, $perm('settings', 'manage'), $csrf]);
$router->get('/settings/networks', [SettingsController::class, 'networks'], [$auth, $perm('settings', 'manage')]);
$router->post('/settings/networks', [SettingsController::class, 'storeNetwork'], [$auth, $perm('settings', 'manage'), $csrf]);
$router->post('/settings/networks/{id}/toggle', [SettingsController::class, 'toggleNetwork'], [$auth, $perm('settings', 'manage'), $csrf]);
$router->get('/settings/eload-products', [SettingsController::class, 'eloadProducts'], [$auth, $perm('settings', 'manage')]);
$router->post('/settings/eload-products', [SettingsController::class, 'storeEloadProduct'], [$auth, $perm('settings', 'manage'), $csrf]);
$router->post('/settings/eload-products/{id}', [SettingsController::class, 'updateEloadProduct'], [$auth, $perm('settings', 'manage'), $csrf]);
$router->post('/settings/eload-products/{id}/toggle', [SettingsController::class, 'toggleEloadProduct'], [$auth, $perm('settings', 'manage'), $csrf]);
$router->get('/settings/gcash-brackets', [SettingsController::class, 'gcashBrackets'], [$auth, $perm('settings', 'manage')]);
$router->post('/settings/gcash-brackets', [SettingsController::class, 'storeGcashBracket'], [$auth, $perm('settings', 'manage'), $csrf]);
$router->post('/settings/gcash-brackets/{id}/delete', [SettingsController::class, 'deleteGcashBracket'], [$auth, $perm('settings', 'manage'), $csrf]);
$router->get('/settings/expense-categories', [SettingsController::class, 'expenseCategories'], [$auth, $perm('settings', 'manage')]);
$router->post('/settings/expense-categories', [SettingsController::class, 'storeExpenseCategory'], [$auth, $perm('settings', 'manage'), $csrf]);
$router->post('/settings/expense-categories/{id}/delete', [SettingsController::class, 'deleteExpenseCategory'], [$auth, $perm('settings', 'manage'), $csrf]);
$router->post('/settings/security', [SettingsController::class, 'updateSecurity'], [$auth, $perm('settings', 'manage'), $csrf]);
$router->get('/settings/backup', [SettingsController::class, 'downloadBackup'], [$auth, $perm('settings', 'manage')]);

// -- Audit log -----------------------------------------------------------------
$router->get('/audit-log', [AuditController::class, 'index'], [$auth, $perm('audit_log', 'view')]);
