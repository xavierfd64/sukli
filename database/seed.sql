-- Sukli — A Store System
-- Baseline seed data: roles, permissions, a demo organization/store, an
-- initial Owner account, default feature flags, payment methods, expense
-- categories, networks, categories, and a handful of sample products so the
-- POS/Inventory screens are demoable out of the box.
--
-- IMPORTANT: change the Owner password immediately after first login.
-- Seeded login: username "owner" / password "Owner@12345"

SET NAMES utf8mb4;

INSERT INTO roles (id, store_id, role_key, name, description, is_system) VALUES
    (1, NULL, 'owner', 'Owner', 'Full access: settings, feature management, users, reports, all records.', 1),
    (2, NULL, 'manager', 'Manager', 'Most operational access with limited sensitive settings.', 1),
    (3, NULL, 'cashier', 'Cashier', 'POS access and limited operational records only.', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

-- Permission catalog: what the app can enforce server-side, grouped by
-- module. Every module/action pair below is checked via Auth::can() at the
-- routes wired to PermissionMiddleware (see routes/web.php); other routes
-- keep their existing Owner/Manager-only or open-to-any-logged-in-user
-- protection, which already matches the defaults seeded below.
INSERT INTO permissions (id, module, action, label, sort_order) VALUES
    (1, 'pos', 'view', 'View POS', 1),
    (2, 'pos', 'create_sale', 'Create Sale', 2),
    (3, 'pos', 'cancel_transaction', 'Cancel Transaction', 3),
    (4, 'inventory', 'view', 'View Inventory', 1),
    (5, 'inventory', 'add', 'Add Product', 2),
    (6, 'inventory', 'edit', 'Edit Product', 3),
    (7, 'inventory', 'delete', 'Archive/Delete Product', 4),
    (8, 'income', 'view', 'View Income', 1),
    (9, 'expenses', 'view', 'View Expenses', 1),
    (10, 'expenses', 'add', 'Add Expense', 2),
    (11, 'expenses', 'delete', 'Delete Expense', 3),
    (28, 'expenses', 'edit', 'Edit Expense', 4),
    (12, 'eload', 'view', 'View E-Load', 1),
    (13, 'eload', 'add', 'Add E-Load Transaction', 2),
    (14, 'gcash', 'view', 'View GCash', 1),
    (15, 'gcash', 'add', 'Add GCash Transaction', 2),
    (16, 'utang', 'view', 'View Utang', 1),
    (17, 'utang', 'record_payment', 'Record Utang Payment', 2),
    (18, 'customers', 'view', 'View Customers', 1),
    (19, 'customers', 'add', 'Add Customer', 2),
    (20, 'customers', 'edit', 'Edit Customer', 3),
    (21, 'suppliers', 'view', 'View Suppliers', 1),
    (22, 'suppliers', 'add', 'Add Supplier', 2),
    (23, 'suppliers', 'edit', 'Edit Supplier', 3),
    (24, 'reports', 'view', 'View Reports', 1),
    (25, 'users', 'manage', 'Manage Users & Roles', 1),
    (26, 'settings', 'manage', 'Manage Settings', 1),
    (27, 'audit_log', 'view', 'View Audit Log', 1)
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- Default matrix mirrors this build's pre-RBAC route protection exactly, so
-- seeding it changes nothing about current behavior until an admin edits a
-- role's permissions from Settings -> Roles & Permissions.
INSERT INTO role_permissions (role_id, permission_id, allowed)
SELECT 1, id, 1 FROM permissions -- Owner: everything
ON DUPLICATE KEY UPDATE allowed = VALUES(allowed);

INSERT INTO role_permissions (role_id, permission_id, allowed)
SELECT 2, id, 1 FROM permissions WHERE module NOT IN ('users', 'settings', 'audit_log')
ON DUPLICATE KEY UPDATE allowed = VALUES(allowed);
INSERT INTO role_permissions (role_id, permission_id, allowed)
SELECT 2, id, 0 FROM permissions WHERE module IN ('users', 'settings', 'audit_log')
ON DUPLICATE KEY UPDATE allowed = VALUES(allowed);

INSERT INTO role_permissions (role_id, permission_id, allowed)
SELECT 3, id, 1 FROM permissions
WHERE (module, action) IN (
    ('pos','view'), ('pos','create_sale'),
    ('inventory','view'),
    ('income','view'),
    ('expenses','view'), ('expenses','add'),
    ('eload','view'), ('eload','add'),
    ('gcash','view'), ('gcash','add'),
    ('utang','view'), ('utang','record_payment'),
    ('customers','view'), ('customers','add'), ('customers','edit')
)
ON DUPLICATE KEY UPDATE allowed = VALUES(allowed);
INSERT INTO role_permissions (role_id, permission_id, allowed)
SELECT 3, id, 0 FROM permissions
WHERE (module, action) NOT IN (
    ('pos','view'), ('pos','create_sale'),
    ('inventory','view'),
    ('income','view'),
    ('expenses','view'), ('expenses','add'),
    ('eload','view'), ('eload','add'),
    ('gcash','view'), ('gcash','add'),
    ('utang','view'), ('utang','record_payment'),
    ('customers','view'), ('customers','add'), ('customers','edit')
)
ON DUPLICATE KEY UPDATE allowed = VALUES(allowed);

INSERT INTO organizations (id, name) VALUES (1, 'Sukli Demo Organization')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO stores (id, organization_id, name, address, phone, currency_symbol, tax_rate, receipt_footer)
VALUES (1, 1, 'Sukli Sari-Sari Store', 'Barangay Sample, Philippines', '0917-000-0000', '₱', 0.00, 'Salamat po sa inyong pagbili!')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Password hash for "Owner@12345" (bcrypt / PASSWORD_DEFAULT)
INSERT INTO users (id, organization_id, store_id, role_id, name, username, email, password_hash, status)
VALUES (1, 1, 1, 1, 'Store Owner', 'owner', 'owner@example.com',
        '$2y$12$vk3outGvxTzZMTsvl.QMeeBsC7um/aj58/SCbtrchVhPa1qLA4.k6', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO user_store_access (user_id, store_id) VALUES (1, 1)
ON DUPLICATE KEY UPDATE store_id = VALUES(store_id);

INSERT INTO feature_settings (store_id, feature_key, is_enabled, show_in_nav, show_in_dashboard) VALUES
    (1, 'eload', 1, 1, 1),
    (1, 'gcash', 1, 1, 1),
    (1, 'utang', 1, 1, 1)
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);

INSERT INTO payment_methods (store_id, method_key, name, is_enabled, sort_order) VALUES
    (1, 'cash', 'Cash', 1, 1),
    (1, 'gcash', 'GCash', 1, 2),
    (1, 'utang', 'Utang', 1, 3),
    (1, 'ewallet', 'E-Wallet', 0, 4),
    (1, 'bank_transfer', 'Bank Transfer', 0, 5),
    (1, 'other', 'Other', 0, 6)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO system_settings (store_id, setting_key, setting_value) VALUES
    (1, 'store_name', 'Sukli Sari-Sari Store'),
    (1, 'business_currency', 'PHP'),
    (1, 'date_format', 'M d, Y'),
    (1, 'receipt_header', 'Sukli Sari-Sari Store'),
    (1, 'receipt_footer', 'Salamat po sa inyong pagbili!'),
    (1, 'receipt_show_address', '1'),
    (1, 'receipt_show_phone', '1'),
    (1, 'receipt_show_logo', '1'),
    (1, 'auto_print_receipt', '0'),
    (1, 'low_stock_threshold_default', '5')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO product_categories (id, store_id, name) VALUES
    (1, 1, 'Beverages'),
    (2, 1, 'Snacks'),
    (3, 1, 'Canned Goods'),
    (4, 1, 'Noodles'),
    (5, 1, 'Personal Care'),
    (6, 1, 'Household'),
    (7, 1, 'Others')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO expense_categories (store_id, name) VALUES
    (1, 'Restock / Supplies'),
    (1, 'Utilities'),
    (1, 'Rent'),
    (1, 'Transportation'),
    (1, 'Salary'),
    (1, 'Other')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO networks (store_id, name, is_enabled, sort_order) VALUES
    (1, 'Globe', 1, 1),
    (1, 'Smart', 1, 2),
    (1, 'TM', 1, 3),
    (1, 'TNT', 1, 4),
    (1, 'DITO', 1, 5)
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);

INSERT INTO gcash_charge_brackets (store_id, min_amount, max_amount, charge, sort_order) VALUES
    (1, 1, 500, 10, 1),
    (1, 501, 1000, 20, 2),
    (1, 1001, 5000, 50, 3),
    (1, 5001, NULL, 100, 4)
ON DUPLICATE KEY UPDATE charge = VALUES(charge);

INSERT INTO products (store_id, category_id, name, barcode, unit, cost_price, selling_price, current_stock, min_stock) VALUES
    (1, 1, 'Coca-Cola 1.5L', '4800012345678', 'bottle', 45.00, 75.00, 12, 5),
    (1, 1, 'Sprite 1.5L', '4800012345685', 'bottle', 45.00, 75.00, 5, 5),
    (1, 1, 'Absolute Water 1L', '4800098765432', 'bottle', 12.00, 20.00, 24, 10),
    (1, 4, 'Lucky Me Pancit Canton', '4807777011129', 'pack', 10.50, 18.00, 5, 5),
    (1, 3, 'San Marino Sardines 155g', '4809012341214', 'can', 18.00, 28.00, 2, 5),
    (1, 1, 'Nescafe 3in1 (10s)', '4800361002356', 'pack', 40.00, 55.00, 0, 5),
    (1, 2, 'Skyflakes Crackers 25g', '4800011112223', 'pack', 7.00, 10.00, 28, 10),
    (1, 1, 'Bear Brand 33g', '4800361456789', 'sachet', 12.00, 15.00, 15, 5),
    (1, 5, 'Surf Bar 155g', '4800543212345', 'bar', 16.00, 20.00, 6, 5),
    (1, 6, 'Downy Sachet 24ml', '4801112223334', 'sachet', 6.00, 10.00, 3, 5)
ON DUPLICATE KEY UPDATE selling_price = VALUES(selling_price);

-- Default subscription plan catalog. Every value here is editable later by
-- Platform Admin (Settings -> Plans) — nothing in the app hardcodes these
-- names, prices, or limits; this is just a sensible starting point. NULL on
-- a max_* column means unlimited.
INSERT INTO subscription_plans (id, slug, name, description, monthly_price, yearly_price, max_branches, max_users, max_products, max_transactions_per_month, is_active, sort_order) VALUES
    (1, 'trial', 'Free Trial', 'Full access during your trial period.', 0.00, 0.00, 1, 3, 100, 500, 1, 0),
    (2, 'basic', 'Basic', 'For small single-branch stores.', 499.00, 4990.00, 1, 3, NULL, NULL, 1, 1),
    (3, 'business', 'Business', 'For growing multi-branch stores.', 1499.00, 14990.00, 5, 15, NULL, NULL, 1, 2),
    (4, 'enterprise', 'Enterprise', 'For larger businesses at unlimited scale.', 4999.00, 49990.00, NULL, NULL, NULL, NULL, 1, 3)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

-- Platform-wide defaults (Platform Admin can change these).
INSERT INTO platform_settings (setting_key, setting_value) VALUES
    ('trial_days', '14'),
    ('platform_name', 'Sukli')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
