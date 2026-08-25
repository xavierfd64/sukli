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
use Sukli\Controllers\SettingsController;
use Sukli\Controllers\SupplierController;
use Sukli\Controllers\UserController;
use Sukli\Controllers\UtangController;
use Sukli\Middleware\AuthMiddleware;
use Sukli\Middleware\CsrfMiddleware;
use Sukli\Middleware\FeatureMiddleware;
use Sukli\Middleware\GuestMiddleware;
use Sukli\Middleware\RoleMiddleware;

$auth = AuthMiddleware::handle();
$guest = GuestMiddleware::handle();
$csrf = CsrfMiddleware::handle();
$ownerOnly = RoleMiddleware::only(['owner']);
$ownerManager = RoleMiddleware::only(['owner', 'manager']);
$eloadOn = FeatureMiddleware::require('eload');
$gcashOn = FeatureMiddleware::require('gcash');
$utangOn = FeatureMiddleware::require('utang');

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
$router->get('/pos', [PosController::class, 'index'], [$auth]);
$router->post('/pos/checkout', [PosController::class, 'checkout'], [$auth, $csrf]);
$router->get('/pos/receipt/{id}', [PosController::class, 'receipt'], [$auth]);

// -- Inventory --------------------------------------------------------------
$router->get('/inventory', [InventoryController::class, 'index'], [$auth]);
$router->get('/inventory/create', [InventoryController::class, 'create'], [$auth, $ownerManager]);
$router->post('/inventory', [InventoryController::class, 'store'], [$auth, $ownerManager, $csrf]);
$router->get('/inventory/{id}/edit', [InventoryController::class, 'edit'], [$auth, $ownerManager]);
$router->post('/inventory/{id}', [InventoryController::class, 'update'], [$auth, $ownerManager, $csrf]);
$router->post('/inventory/{id}/archive', [InventoryController::class, 'archive'], [$auth, $ownerManager, $csrf]);
$router->post('/inventory/{id}/adjust', [InventoryController::class, 'adjustStock'], [$auth, $ownerManager, $csrf]);
$router->post('/inventory/categories', [InventoryController::class, 'storeCategory'], [$auth, $ownerManager, $csrf]);
$router->get('/inventory/export.csv', [InventoryController::class, 'exportCsv'], [$auth, $ownerManager]);
$router->post('/inventory/import', [InventoryController::class, 'importCsv'], [$auth, $ownerManager, $csrf]);

// -- Income / Expenses --------------------------------------------------------
$router->get('/income', [IncomeController::class, 'index'], [$auth]);
$router->post('/income', [IncomeController::class, 'store'], [$auth, $csrf]);
$router->post('/income/{id}', [IncomeController::class, 'update'], [$auth, $ownerManager, $csrf]);
$router->post('/income/{id}/delete', [IncomeController::class, 'destroy'], [$auth, $ownerManager, $csrf]);

$router->get('/expenses', [ExpenseController::class, 'index'], [$auth]);
$router->post('/expenses', [ExpenseController::class, 'store'], [$auth, $csrf]);
$router->post('/expenses/{id}', [ExpenseController::class, 'update'], [$auth, $ownerManager, $csrf]);
$router->post('/expenses/{id}/delete', [ExpenseController::class, 'destroy'], [$auth, $ownerManager, $csrf]);

// -- E-Load / GCash (feature-flagged, recording only) ------------------------
$router->get('/eload', [EloadController::class, 'index'], [$auth, $eloadOn]);
$router->post('/eload', [EloadController::class, 'store'], [$auth, $eloadOn, $csrf]);

$router->get('/gcash', [GcashController::class, 'index'], [$auth, $gcashOn]);
$router->post('/gcash', [GcashController::class, 'store'], [$auth, $gcashOn, $csrf]);

// -- Utang / Credit (feature-flagged) -----------------------------------------
$router->get('/utang', [UtangController::class, 'index'], [$auth, $utangOn]);
$router->get('/utang/{customerId}', [UtangController::class, 'show'], [$auth, $utangOn]);
$router->post('/utang/{customerId}/payment', [UtangController::class, 'recordPayment'], [$auth, $utangOn, $csrf]);

// -- Customers / Suppliers ----------------------------------------------------
$router->get('/customers', [CustomerController::class, 'index'], [$auth]);
$router->get('/customers/search', [CustomerController::class, 'search'], [$auth]);
$router->get('/customers/export.csv', [CustomerController::class, 'exportCsv'], [$auth]);
$router->post('/customers', [CustomerController::class, 'store'], [$auth, $csrf]);
$router->post('/customers/{id}', [CustomerController::class, 'update'], [$auth, $csrf]);

$router->get('/suppliers', [SupplierController::class, 'index'], [$auth, $ownerManager]);
$router->post('/suppliers', [SupplierController::class, 'store'], [$auth, $ownerManager, $csrf]);
$router->post('/suppliers/{id}', [SupplierController::class, 'update'], [$auth, $ownerManager, $csrf]);

// -- Users (owner only) ----------------------------------------------------
$router->get('/users', [UserController::class, 'index'], [$auth, $ownerOnly]);
$router->post('/users', [UserController::class, 'store'], [$auth, $ownerOnly, $csrf]);
$router->post('/users/{id}', [UserController::class, 'update'], [$auth, $ownerOnly, $csrf]);
$router->post('/users/{id}/deactivate', [UserController::class, 'deactivate'], [$auth, $ownerOnly, $csrf]);

// -- Reports -----------------------------------------------------------------
$router->get('/reports', [ReportController::class, 'index'], [$auth, $ownerManager]);

// -- Settings / Feature Management (owner only) --------------------------------
$router->get('/settings', [SettingsController::class, 'index'], [$auth, $ownerOnly]);
$router->post('/settings/general', [SettingsController::class, 'updateGeneral'], [$auth, $ownerOnly, $csrf]);
$router->get('/settings/features', [SettingsController::class, 'features'], [$auth, $ownerOnly]);
$router->post('/settings/features', [SettingsController::class, 'updateFeatures'], [$auth, $ownerOnly, $csrf]);
$router->get('/settings/payment-methods', [SettingsController::class, 'paymentMethods'], [$auth, $ownerOnly]);
$router->post('/settings/payment-methods', [SettingsController::class, 'updatePaymentMethods'], [$auth, $ownerOnly, $csrf]);
$router->post('/settings/security', [SettingsController::class, 'updateSecurity'], [$auth, $ownerOnly, $csrf]);
$router->get('/settings/backup', [SettingsController::class, 'downloadBackup'], [$auth, $ownerOnly]);

// -- Audit log (owner only) ---------------------------------------------------
$router->get('/audit-log', [AuditController::class, 'index'], [$auth, $ownerOnly]);
