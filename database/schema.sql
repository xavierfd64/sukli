-- Sukli — A Store System
-- Core database schema (MySQL 5.7+/8, MariaDB 10.3+ compatible)
-- Ownership chain: organizations -> stores -> everything else.
-- The first release ships one organization/one store per customer, but every
-- store-scoped table carries store_id so multi-store expansion needs no redesign.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Organizations / Stores
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS organizations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    address VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    logo_path VARCHAR(255) NULL,
    currency_code VARCHAR(3) NOT NULL DEFAULT 'PHP',
    currency_symbol VARCHAR(5) NOT NULL DEFAULT '₱',
    timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Manila',
    receipt_footer VARCHAR(255) NULL,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_stores_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Users / Roles / Access
-- ---------------------------------------------------------------------------

-- store_id NULL = one of the 3 built-in system roles (Owner/Manager/
-- Cashier), shared across every store; store_id set = a custom role an
-- Owner created for their store. is_system protects the built-ins from
-- rename/delete so existing role-based behavior can never be broken from
-- the Roles screen.
CREATE TABLE IF NOT EXISTS roles (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NULL,
    role_key VARCHAR(30) NOT NULL,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_roles_store_key (store_id, role_key),
    CONSTRAINT fk_roles_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed catalog of permission checks the app enforces server-side. Not
-- store-scoped -- this is "what the system supports", not per-store data.
CREATE TABLE IF NOT EXISTS permissions (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(40) NOT NULL,
    action VARCHAR(40) NOT NULL,
    label VARCHAR(100) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_permissions_module_action (module, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id SMALLINT UNSIGNED NOT NULL,
    permission_id SMALLINT UNSIGNED NOT NULL,
    allowed TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_role_permission (role_id, permission_id),
    CONSTRAINT fk_role_perm_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_perm_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    store_id INT UNSIGNED NULL,
    role_id SMALLINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    email VARCHAR(150) NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_users_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reserved for future multi-store access grants; v1 auto-populates one row
-- per user from users.store_id so the query surface is already future-ready.
CREATE TABLE IF NOT EXISTS user_store_access (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    store_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_store (user_id, store_id),
    CONSTRAINT fk_usa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_usa_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_attempts_username_time (username, attempted_at),
    KEY idx_login_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Products / Inventory
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS product_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_category_store_name (store_id, name),
    CONSTRAINT fk_categories_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- At least one of company_name / contact_first_name / contact_last_name must
-- be present -- enforced in SupplierController, not by a DB constraint (kept
-- portable across MySQL versions that may not support CHECK constraints).
-- Defined here (ahead of products) so products.supplier_id can reference it.
CREATE TABLE IF NOT EXISTS suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    company_name VARCHAR(150) NULL,
    contact_first_name VARCHAR(100) NULL,
    contact_last_name VARCHAR(100) NULL,
    contact_number VARCHAR(30) NULL,
    address VARCHAR(255) NULL,
    notes VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_suppliers_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NULL,
    supplier_id INT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    barcode VARCHAR(64) NULL,
    image_path VARCHAR(255) NULL,
    unit VARCHAR(30) NOT NULL DEFAULT 'pc',
    cost_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    selling_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    current_stock INT NOT NULL DEFAULT 0,
    min_stock INT NOT NULL DEFAULT 0,
    expiry_date DATE NULL,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_products_store_status (store_id, status),
    KEY idx_products_barcode (store_id, barcode),
    KEY idx_products_supplier (supplier_id),
    CONSTRAINT fk_products_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    type ENUM('initial','purchase_in','sale_out','adjustment_in','adjustment_out','void_reversal') NOT NULL,
    quantity INT NOT NULL,
    reference_type VARCHAR(30) NULL,
    reference_id BIGINT UNSIGNED NULL,
    note VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_inv_txn_product (product_id, created_at),
    KEY idx_inv_txn_store (store_id, created_at),
    CONSTRAINT fk_inv_txn_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_txn_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_txn_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Customers / Utang (credit)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NULL,
    contact_number VARCHAR(30) NULL,
    address VARCHAR(255) NULL,
    notes VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_customers_store (store_id, status),
    CONSTRAINT fk_customers_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_credit_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL UNIQUE,
    credit_limit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    outstanding_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cca_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    CONSTRAINT fk_cca_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Sales / POS
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS sales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    sale_number VARCHAR(30) NOT NULL,
    customer_id INT UNSIGNED NULL,
    cashier_id INT UNSIGNED NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('cash','gcash','utang','ewallet','bank_transfer','other','split') NOT NULL,
    amount_tendered DECIMAL(10,2) NULL,
    change_amount DECIMAL(10,2) NULL,
    status ENUM('completed','voided') NOT NULL DEFAULT 'completed',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sales_store_number (store_id, sale_number),
    KEY idx_sales_store_created (store_id, created_at),
    CONSTRAINT fk_sales_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    CONSTRAINT fk_sales_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_sales_cashier FOREIGN KEY (cashier_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sale_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    KEY idx_sale_items_sale (sale_id),
    CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    CONSTRAINT fk_sale_items_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id BIGINT UNSIGNED NOT NULL,
    method ENUM('cash','gcash','utang','ewallet','bank_transfer','other') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reference_no VARCHAR(60) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_payments_sale (sale_id),
    CONSTRAINT fk_payments_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS utang_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    due_date DATE NULL,
    status ENUM('outstanding','partially_paid','paid') NOT NULL DEFAULT 'outstanding',
    note VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_utang_txn_customer (customer_id, created_at),
    KEY idx_utang_txn_store (store_id, created_at),
    CONSTRAINT fk_utang_txn_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    CONSTRAINT fk_utang_txn_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_utang_txn_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL,
    CONSTRAINT fk_utang_txn_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS utang_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    utang_transaction_id BIGINT UNSIGNED NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash','gcash','ewallet','bank_transfer','other') NOT NULL DEFAULT 'cash',
    note VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_utang_pay_customer (customer_id, created_at),
    KEY idx_utang_pay_store (store_id, created_at),
    CONSTRAINT fk_utang_pay_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    CONSTRAINT fk_utang_pay_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_utang_pay_txn FOREIGN KEY (utang_transaction_id) REFERENCES utang_transactions(id) ON DELETE SET NULL,
    CONSTRAINT fk_utang_pay_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Income / Expenses
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS income_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    income_date DATE NOT NULL,
    category VARCHAR(80) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_income_store_date (store_id, income_date),
    CONSTRAINT fk_income_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    CONSTRAINT fk_income_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expense_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    expense_date DATE NOT NULL,
    category VARCHAR(80) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255) NULL,
    receipt_attachment_path VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_expense_store_date (store_id, expense_date),
    CONSTRAINT fk_expense_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    CONSTRAINT fk_expense_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- E-Load / GCash (recording-only, no telecom/GCash API integration)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS eload_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    transacted_at DATETIME NOT NULL,
    customer_name VARCHAR(150) NULL,
    mobile_number VARCHAR(30) NULL,
    network VARCHAR(50) NULL,
    load_amount DECIMAL(10,2) NOT NULL,
    amount_received DECIMAL(10,2) NOT NULL,
    cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    profit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_eload_store_time (store_id, transacted_at),
    CONSTRAINT fk_eload_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    CONSTRAINT fk_eload_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gcash_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    transacted_at DATETIME NOT NULL,
    type ENUM('cash_in','cash_out') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    service_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    customer_reference VARCHAR(150) NULL,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_gcash_store_time (store_id, transacted_at),
    CONSTRAINT fk_gcash_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    CONSTRAINT fk_gcash_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Suppliers
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- E-Load networks / GCash charge brackets (admin-manageable lists)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS networks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_networks_store_name (store_id, name),
    CONSTRAINT fk_networks_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- E-Load product catalog, per network. selling_price is always a value the
-- admin sets explicitly (never derived from cost + additional_charge) —
-- additional_charge is only a hint used to compute a *suggested* price in
-- the UI. earnings (selling_price - cost) is computed on read, not stored,
-- so it can never drift from the two values it's derived from.
CREATE TABLE IF NOT EXISTS eload_products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    network VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    additional_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    selling_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_eload_products_store (store_id, network),
    CONSTRAINT fk_eload_products_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- max_amount NULL = no upper bound (open-ended top bracket).
CREATE TABLE IF NOT EXISTS gcash_charge_brackets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    min_amount DECIMAL(10,2) NOT NULL,
    max_amount DECIMAL(10,2) NULL,
    charge DECIMAL(10,2) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_gcash_bracket_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Expense categories (admin-manageable list backing the free-text
-- expense_records.category column -- kept as text there for zero-risk
-- compatibility with existing reports/grouping queries)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS expense_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    UNIQUE KEY uq_expense_category_store_name (store_id, name),
    CONSTRAINT fk_expense_category_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Payment methods (POS / split payment / Utang payment availability)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS payment_methods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    method_key ENUM('cash','gcash','utang','ewallet','bank_transfer','other') NOT NULL,
    name VARCHAR(50) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_payment_method_store_key (store_id, method_key),
    CONSTRAINT fk_payment_method_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Feature Management / System Settings
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS feature_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    feature_key ENUM('eload','gcash','utang') NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    show_in_nav TINYINT(1) NOT NULL DEFAULT 1,
    show_in_dashboard TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_feature_store_key (store_id, feature_key),
    CONSTRAINT fk_feature_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    setting_key VARCHAR(80) NOT NULL,
    setting_value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_setting_store_key (store_id, setting_key),
    CONSTRAINT fk_settings_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Audit Log
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NULL,
    store_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,
    module VARCHAR(60) NOT NULL,
    related_type VARCHAR(60) NULL,
    related_id BIGINT UNSIGNED NULL,
    old_values TEXT NULL,
    new_values TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_store_time (store_id, created_at),
    KEY idx_audit_user_time (user_id, created_at),
    KEY idx_audit_module (module, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
