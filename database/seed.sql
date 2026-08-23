-- Sukli — A Store System
-- Baseline seed data: roles, a demo organization/store, an initial Owner
-- account, default feature flags, categories, and a handful of sample
-- products so the POS/Inventory screens are demoable out of the box.
--
-- IMPORTANT: change the Owner password immediately after first login.
-- Seeded login: username "owner" / password "Owner@12345"

SET NAMES utf8mb4;

INSERT INTO roles (id, role_key, name, description) VALUES
    (1, 'owner', 'Owner', 'Full access: settings, feature management, users, reports, all records.'),
    (2, 'manager', 'Manager', 'Most operational access with limited sensitive settings.'),
    (3, 'cashier', 'Cashier', 'POS access and limited operational records only.')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

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

INSERT INTO system_settings (store_id, setting_key, setting_value) VALUES
    (1, 'store_name', 'Sukli Sari-Sari Store'),
    (1, 'business_currency', 'PHP'),
    (1, 'date_format', 'M d, Y'),
    (1, 'payment_methods_enabled', 'cash,gcash,utang'),
    (1, 'receipt_header', 'Sukli Sari-Sari Store'),
    (1, 'receipt_footer', 'Salamat po sa inyong pagbili!'),
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
