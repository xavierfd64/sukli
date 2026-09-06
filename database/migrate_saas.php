<?php
/**
 * One-time upgrade for an EXISTING Sukli installation (pre-SaaS) to the
 * multi-tenant/multi-branch/subscription schema. Safe to run more than
 * once — every step checks before it acts. Never deletes or overwrites
 * existing business data (products, sales, customers, etc.); it only adds
 * new columns/tables and a small amount of bookkeeping so the existing
 * organization keeps working exactly as before, now as a normal tenant.
 *
 * What it does, for every organization that doesn't already have one:
 *   - Flags that organization's oldest store as its Main Branch.
 *   - Creates a permanently-active ("grandfathered") subscription, NOT a
 *     ticking trial — an already-running store should never suddenly find
 *     itself locked out by a trial countdown it never signed up for.
 *     Platform Admin can change an organization's plan/status at any time
 *     after this.
 *
 * A fresh install never needs this script — schema.sql already includes
 * every column/table below, and the installer creates a proper trial
 * subscription for the one organization it makes.
 *
 * Usage:
 *   php database/migrate_saas.php
 *   php database/migrate_saas.php --platform-admin=someusername
 *       Also grants that existing user Platform Super Admin access.
 *       Run this deliberately, for one specific person — it is never done
 *       automatically, since platform admin can see every tenant's data.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script may only be run from the command line.');
}

require __DIR__ . '/../app/Core/Installer.php';
require __DIR__ . '/../app/Core/Env.php';

Sukli\Core\Env::load(__DIR__ . '/../.env');

$installedDb = Sukli\Core\Env::installed()['db'] ?? [];

$host = $installedDb['host'] ?? Sukli\Core\Env::get('DB_HOST', '127.0.0.1');
$port = $installedDb['port'] ?? Sukli\Core\Env::get('DB_PORT', '3306');
$database = $installedDb['database'] ?? Sukli\Core\Env::get('DB_DATABASE', 'sukli');
$username = $installedDb['username'] ?? Sukli\Core\Env::get('DB_USERNAME', 'root');
$password = $installedDb['password'] ?? Sukli\Core\Env::get('DB_PASSWORD', '');

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Database connection failed: {$e->getMessage()}\n");
    exit(1);
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (columnExists($pdo, $table, $column)) {
        echo "  - {$table}.{$column} already exists, skipping\n";
        return;
    }
    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
    echo "  + added {$table}.{$column}\n";
}

echo "Step 1/5: adding new columns to existing tables...\n";
addColumnIfMissing($pdo, 'organizations', 'slug', 'slug VARCHAR(80) NULL UNIQUE');
addColumnIfMissing($pdo, 'stores', 'branch_code', 'branch_code VARCHAR(30) NULL');
addColumnIfMissing($pdo, 'stores', 'is_main_branch', 'is_main_branch TINYINT(1) NOT NULL DEFAULT 0');
addColumnIfMissing($pdo, 'users', 'is_platform_admin', 'is_platform_admin TINYINT(1) NOT NULL DEFAULT 0');

echo "Step 2/5: creating new SaaS tables (no-op if they already exist)...\n";
$pdo->exec("CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(40) NOT NULL,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    yearly_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    max_branches SMALLINT UNSIGNED NULL,
    max_users SMALLINT UNSIGNED NULL,
    max_products INT UNSIGNED NULL,
    max_transactions_per_month INT UNSIGNED NULL,
    features TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subscription_plans_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    subscription_plan_id INT UNSIGNED NOT NULL,
    billing_period ENUM('trial','monthly','yearly') NOT NULL DEFAULT 'trial',
    status ENUM('trial','active','expired','suspended','cancelled') NOT NULL DEFAULT 'trial',
    trial_ends_at DATETIME NULL,
    current_period_start DATETIME NULL,
    current_period_end DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subscriptions_organization (organization_id),
    KEY idx_subscriptions_status (status),
    CONSTRAINT fk_subscriptions_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_subscriptions_plan FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS subscription_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    subscription_id INT UNSIGNED NOT NULL,
    subscription_plan_id INT UNSIGNED NOT NULL,
    billing_period ENUM('monthly','yearly') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(30) NOT NULL,
    reference_no VARCHAR(100) NULL,
    proof_path VARCHAR(255) NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    submitted_by INT UNSIGNED NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sub_payments_org_status (organization_id, status),
    CONSTRAINT fk_sub_payments_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_sub_payments_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
    CONSTRAINT fk_sub_payments_plan FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sub_payments_submitted_by FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_sub_payments_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS platform_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(80) NOT NULL,
    setting_value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_platform_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "Step 3/5: seeding plan catalog and platform defaults...\n";
$pdo->exec("INSERT INTO subscription_plans (id, slug, name, description, monthly_price, yearly_price, max_branches, max_users, max_products, max_transactions_per_month, is_active, sort_order) VALUES
    (1, 'trial', 'Free Trial', 'Full access during your trial period.', 0.00, 0.00, 1, 3, 100, 500, 1, 0),
    (2, 'basic', 'Basic', 'For small single-branch stores.', 499.00, 4990.00, 1, 3, NULL, NULL, 1, 1),
    (3, 'business', 'Business', 'For growing multi-branch stores.', 1499.00, 14990.00, 5, 15, NULL, NULL, 1, 2),
    (4, 'enterprise', 'Enterprise', 'For larger businesses at unlimited scale.', 4999.00, 49990.00, NULL, NULL, NULL, NULL, 1, 3)
    ON DUPLICATE KEY UPDATE name = VALUES(name)");
$pdo->exec("INSERT INTO platform_settings (setting_key, setting_value) VALUES
    ('trial_days', '14'), ('platform_name', 'Sukli'), ('theme_color', '#16a34a'), ('theme_font', 'system')
    ON DUPLICATE KEY UPDATE setting_value = setting_value");

echo "Step 4/5: grandfathering existing organizations onto an active subscription...\n";
$businessPlanId = (int) $pdo->query("SELECT id FROM subscription_plans WHERE slug = 'business'")->fetchColumn();

$orgs = $pdo->query("SELECT id, name FROM organizations")->fetchAll(PDO::FETCH_ASSOC);
foreach ($orgs as $org) {
    $orgId = (int) $org['id'];

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM stores WHERE organization_id = ? AND is_main_branch = 1");
    $countStmt->execute([$orgId]);
    if ((int) $countStmt->fetchColumn() === 0) {
        $firstStoreStmt = $pdo->prepare("SELECT id, branch_code FROM stores WHERE organization_id = ? ORDER BY id ASC LIMIT 1");
        $firstStoreStmt->execute([$orgId]);
        $firstStore = $firstStoreStmt->fetch(PDO::FETCH_ASSOC);
        if ($firstStore) {
            $update = $pdo->prepare(
                "UPDATE stores SET is_main_branch = 1, branch_code = COALESCE(branch_code, 'MAIN') WHERE id = ?"
            );
            $update->execute([$firstStore['id']]);
            echo "  + store #{$firstStore['id']} flagged as Main Branch for organization \"{$org['name']}\"\n";
        }
    }

    $subStmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE organization_id = ?");
    $subStmt->execute([$orgId]);
    if ((int) $subStmt->fetchColumn() === 0) {
        $insert = $pdo->prepare(
            "INSERT INTO subscriptions (organization_id, subscription_plan_id, billing_period, status, current_period_start, current_period_end)
             VALUES (?, ?, 'yearly', 'active', NOW(), DATE_ADD(NOW(), INTERVAL 10 YEAR))"
        );
        $insert->execute([$orgId, $businessPlanId]);
        echo "  + organization \"{$org['name']}\" (#{$orgId}) given an active grandfathered subscription\n";
    } else {
        echo "  - organization \"{$org['name']}\" (#{$orgId}) already has a subscription, skipping\n";
    }
}

echo "Step 5/5: repairing subscriptions.billing_period left stale by an earlier version of approvePaymentAndRenew()...\n";
$stale = $pdo->query(
    "SELECT id, organization_id FROM subscriptions WHERE billing_period = 'trial' AND status != 'trial'"
)->fetchAll(PDO::FETCH_ASSOC);
if ($stale) {
    // Cosmetic-only backfill: billing_period is a display label, not used to
    // compute any date or amount, so defaulting the unknown historical cases
    // to 'monthly' is safe — Platform Admin can correct any that were
    // actually yearly via a plan change, no data is at risk either way.
    $fix = $pdo->prepare("UPDATE subscriptions SET billing_period = 'monthly' WHERE id = ?");
    foreach ($stale as $row) {
        $fix->execute([$row['id']]);
        echo "  + subscription #{$row['id']} (organization #{$row['organization_id']}): billing_period 'trial' -> 'monthly'\n";
    }
} else {
    echo "  - no stale rows found, skipping\n";
}

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--platform-admin=')) {
        $usernameToPromote = substr($arg, strlen('--platform-admin='));
        $stmt = $pdo->prepare("UPDATE users SET is_platform_admin = 1 WHERE username = ?");
        $stmt->execute([$usernameToPromote]);
        if ($stmt->rowCount() > 0) {
            echo "Granted Platform Super Admin access to user \"{$usernameToPromote}\".\n";
        } else {
            fwrite(STDERR, "No user found with username \"{$usernameToPromote}\" — Platform Admin access NOT granted.\n");
        }
    }
}

echo "Done.\n";
