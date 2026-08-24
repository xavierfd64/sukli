<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use PDO;
use PDOException;
use Sukli\Core\Controller;
use Sukli\Core\Installer;
use Sukli\Core\Request;
use Sukli\Core\Session;

/**
 * The first-time setup wizard (WordPress-style: upload files, open the
 * site, get walked through DB config + admin account creation, done).
 *
 * Deliberately self-contained: it never touches Sukli\Core\Database or
 * Sukli\Core\Env (those are keyed to a .env file that may not exist yet),
 * and it never touches any existing Controller/Service. It manages its own
 * short-lived PDO connection sourced from session-held form data across
 * the wizard's steps, and only writes .env / storage/installed.lock at the
 * very end, once the administrator has confirmed everything.
 *
 * Every action refuses to run once Installer::isInstalled() is true — see
 * alreadyInstalled().
 */
class InstallController extends Controller
{
    private const SESSION_DB = '_install_db';
    private const SESSION_ADMIN = '_install_admin';
    private const SESSION_STORE = '_install_store';
    private const SESSION_ORG_ID = '_install_org_id';
    private const SESSION_STORE_ID = '_install_store_id';

    // -- Step pages -----------------------------------------------------

    public function welcome(Request $request): void
    {
        if ($this->alreadyInstalled()) {
            return;
        }
        $this->view('install/welcome', ['pageTitle' => 'Install Sukli', 'step' => 1], 'layouts/install');
    }

    public function showDatabase(Request $request): void
    {
        if ($this->alreadyInstalled()) {
            return;
        }
        $db = Session::get(self::SESSION_DB, []);
        $this->view('install/database', [
            'pageTitle' => 'Database Configuration',
            'step' => 2,
            'error' => Session::flash('install_error'),
            'db' => $db,
        ], 'layouts/install');
    }

    public function saveDatabase(Request $request): void
    {
        if ($this->alreadyInstalled()) {
            return;
        }

        $db = [
            'host' => $request->trimmed('host') ?: 'localhost',
            'port' => $request->trimmed('port') ?: '3306',
            'database' => $request->trimmed('database'),
            'username' => $request->trimmed('username'),
            'password' => (string) $request->input('password', ''),
        ];

        $result = $this->attemptConnection($db);
        if (!$result['ok']) {
            Session::flash('install_error', $result['message']);
            Session::put(self::SESSION_DB, $db);
            $this->redirect('/install/database');
        }

        Session::put(self::SESSION_DB, $db);
        $this->redirect('/install/admin');
    }

    public function showAdmin(Request $request): void
    {
        if ($this->alreadyInstalled()) {
            return;
        }
        if (!Session::get(self::SESSION_DB)) {
            $this->redirect('/install/database');
        }
        $this->view('install/admin', [
            'pageTitle' => 'Create Administrator Account',
            'step' => 3,
            'error' => Session::flash('install_error'),
            'admin' => Session::get(self::SESSION_ADMIN, []),
        ], 'layouts/install');
    }

    public function saveAdmin(Request $request): void
    {
        if ($this->alreadyInstalled()) {
            return;
        }
        if (!Session::get(self::SESSION_DB)) {
            $this->redirect('/install/database');
        }

        $name = $request->trimmed('name');
        $email = $request->trimmed('email');
        $username = $request->trimmed('username');
        $password = (string) $request->input('password', '');
        $confirm = (string) $request->input('confirm_password', '');

        $admin = ['name' => $name, 'email' => $email, 'username' => $username];

        if ($name === '' || $username === '') {
            Session::flash('install_error', 'Owner name and username are required.');
            Session::put(self::SESSION_ADMIN, $admin);
            $this->redirect('/install/admin');
        }
        if (strlen($password) < 8) {
            Session::flash('install_error', 'Password must be at least 8 characters.');
            Session::put(self::SESSION_ADMIN, $admin);
            $this->redirect('/install/admin');
        }
        if ($password !== $confirm) {
            Session::flash('install_error', 'Password and confirmation do not match.');
            Session::put(self::SESSION_ADMIN, $admin);
            $this->redirect('/install/admin');
        }

        $admin['password'] = $password;
        Session::put(self::SESSION_ADMIN, $admin);
        $this->redirect('/install/store');
    }

    public function showStore(Request $request): void
    {
        if ($this->alreadyInstalled()) {
            return;
        }
        if (!Session::get(self::SESSION_ADMIN)) {
            $this->redirect('/install/admin');
        }
        $this->view('install/store', [
            'pageTitle' => 'Store Setup',
            'step' => 4,
            'store' => Session::get(self::SESSION_STORE, []),
        ], 'layouts/install');
    }

    public function saveStore(Request $request): void
    {
        if ($this->alreadyInstalled()) {
            return;
        }
        if (!Session::get(self::SESSION_ADMIN)) {
            $this->redirect('/install/admin');
        }

        Session::put(self::SESSION_STORE, [
            'name' => $request->trimmed('store_name') ?: 'My Store',
            'address' => $request->trimmed('store_address'),
            'phone' => $request->trimmed('contact_number'),
        ]);

        $this->redirect('/install/finish');
    }

    public function finish(Request $request): void
    {
        if ($this->alreadyInstalled()) {
            return;
        }
        if (!Session::get(self::SESSION_STORE)) {
            $this->redirect('/install/store');
        }
        $this->view('install/finish', [
            'pageTitle' => 'Install Sukli',
            'step' => 5,
            'admin' => Session::get(self::SESSION_ADMIN),
            'store' => Session::get(self::SESSION_STORE),
        ], 'layouts/install');
    }

    /** Live "Test Connection" button on the Database step — tests whatever is currently typed, not session state. */
    public function apiTestConnection(Request $request): void
    {
        if (Installer::isInstalled()) {
            $this->json(['ok' => false, 'message' => 'Sukli is already installed.'], 409);
        }

        $db = [
            'host' => $request->trimmed('host') ?: 'localhost',
            'port' => $request->trimmed('port') ?: '3306',
            'database' => $request->trimmed('database'),
            'username' => $request->trimmed('username'),
            'password' => (string) $request->input('password', ''),
        ];

        $result = $this->attemptConnection($db);
        $this->json(['ok' => $result['ok'], 'message' => $result['message']], $result['ok'] ? 200 : 422);
    }

    // -- Live API steps used by the final progress checklist -------------

    public function apiCheckRequirements(Request $request): void
    {
        if (Installer::isInstalled()) {
            $this->json(['ok' => false, 'message' => 'Sukli is already installed.'], 409);
        }

        $checks = [
            'php_version' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'session' => extension_loaded('session'),
            'config_writable' => is_writable(__DIR__ . '/../../config'),
            'storage_writable' => is_writable(__DIR__ . '/../../storage'),
        ];

        $ok = !in_array(false, $checks, true);
        $this->json([
            'ok' => $ok,
            'message' => $ok ? 'System requirements satisfied.' : 'Some system requirements are not met. Check PHP version (8.2+), the pdo_mysql extension, and that the config/ and storage/ folders are writable.',
            'checks' => $checks,
        ]);
    }

    public function apiConnect(Request $request): void
    {
        if (Installer::isInstalled()) {
            $this->json(['ok' => false, 'message' => 'Sukli is already installed.'], 409);
        }

        $db = Session::get(self::SESSION_DB);
        if (!$db) {
            $this->json(['ok' => false, 'message' => 'Database details were lost. Please start over.'], 400);
        }

        $result = $this->attemptConnection($db);
        $this->json(['ok' => $result['ok'], 'message' => $result['message']], $result['ok'] ? 200 : 422);
    }

    public function apiCreateTables(Request $request): void
    {
        if (Installer::isInstalled()) {
            $this->json(['ok' => false, 'message' => 'Sukli is already installed.'], 409);
        }

        $db = Session::get(self::SESSION_DB);
        $store = Session::get(self::SESSION_STORE);
        if (!$db || !$store) {
            $this->json(['ok' => false, 'message' => 'Missing setup data. Please start over.'], 400);
        }

        $result = $this->attemptConnection($db);
        if (!$result['ok']) {
            $this->json($result, 422);
        }
        $pdo = $result['pdo'];

        try {
            $this->runSqlFile($pdo, __DIR__ . '/../../database/schema.sql');

            $pdo->exec("
                INSERT INTO roles (id, role_key, name, description) VALUES
                    (1, 'owner', 'Owner', 'Full access: settings, feature management, users, reports, all records.'),
                    (2, 'manager', 'Manager', 'Most operational access with limited sensitive settings.'),
                    (3, 'cashier', 'Cashier', 'POS access and limited operational records only.')
                ON DUPLICATE KEY UPDATE name = VALUES(name)
            ");

            $orgId = (int) ($pdo->query('SELECT id FROM organizations ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
            if ($orgId === 0) {
                $stmt = $pdo->prepare('INSERT INTO organizations (name) VALUES (?)');
                $stmt->execute([$store['name'] ?: 'My Organization']);
                $orgId = (int) $pdo->lastInsertId();
            }

            $storeId = (int) ($pdo->query('SELECT id FROM stores ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
            if ($storeId === 0) {
                $stmt = $pdo->prepare('INSERT INTO stores (organization_id, name) VALUES (?, ?)');
                $stmt->execute([$orgId, $store['name'] ?: 'My Store']);
                $storeId = (int) $pdo->lastInsertId();
            }

            $featureStmt = $pdo->prepare(
                "INSERT INTO feature_settings (store_id, feature_key, is_enabled, show_in_nav, show_in_dashboard)
                 VALUES (?, ?, 1, 1, 1) ON DUPLICATE KEY UPDATE feature_key = VALUES(feature_key)"
            );
            foreach (['eload', 'gcash', 'utang'] as $key) {
                $featureStmt->execute([$storeId, $key]);
            }

            $settingsStmt = $pdo->prepare(
                "INSERT INTO system_settings (store_id, setting_key, setting_value) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
            foreach ([
                'business_currency' => 'PHP',
                'date_format' => 'M d, Y',
                'payment_methods_enabled' => 'cash,gcash,utang',
                'low_stock_threshold_default' => '5',
            ] as $key => $value) {
                $settingsStmt->execute([$storeId, $key, $value]);
            }

            Session::put(self::SESSION_ORG_ID, $orgId);
            Session::put(self::SESSION_STORE_ID, $storeId);
        } catch (PDOException $e) {
            error_log('[Sukli Installer] create-tables failed: ' . $e->getMessage());
            $this->json(['ok' => false, 'message' => 'Could not set up the database tables. Please check your database user has permission to CREATE TABLE.'], 500);
        }

        $this->json(['ok' => true, 'message' => 'Database tables ready.']);
    }

    public function apiCreateAdmin(Request $request): void
    {
        if (Installer::isInstalled()) {
            $this->json(['ok' => false, 'message' => 'Sukli is already installed.'], 409);
        }

        $db = Session::get(self::SESSION_DB);
        $admin = Session::get(self::SESSION_ADMIN);
        $orgId = Session::get(self::SESSION_ORG_ID);
        $storeId = Session::get(self::SESSION_STORE_ID);

        if (!$db || !$admin || !$orgId || !$storeId) {
            $this->json(['ok' => false, 'message' => 'Missing setup data. Please start over.'], 400);
        }

        $result = $this->attemptConnection($db);
        if (!$result['ok']) {
            $this->json($result, 422);
        }
        $pdo = $result['pdo'];

        try {
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE store_id = ?');
            $countStmt->execute([$storeId]);
            $existingCount = (int) $countStmt->fetchColumn();

            if ($existingCount > 0) {
                $this->json(['ok' => true, 'message' => 'Administrator account already created.']);
            }

            $usernameTaken = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
            $usernameTaken->execute([$admin['username']]);
            if ((int) $usernameTaken->fetchColumn() > 0) {
                $this->json(['ok' => false, 'message' => 'That username is already taken. Go back and choose another.'], 422);
            }

            $ownerRoleId = (int) $pdo->query("SELECT id FROM roles WHERE role_key = 'owner'")->fetchColumn();

            $stmt = $pdo->prepare(
                "INSERT INTO users (organization_id, store_id, role_id, name, username, email, password_hash, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'active')"
            );
            $stmt->execute([
                $orgId, $storeId, $ownerRoleId, $admin['name'], $admin['username'],
                $admin['email'] ?: null, password_hash($admin['password'], PASSWORD_DEFAULT),
            ]);
            $userId = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO user_store_access (user_id, store_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE store_id = VALUES(store_id)')
                ->execute([$userId, $storeId]);
        } catch (PDOException $e) {
            error_log('[Sukli Installer] create-admin failed: ' . $e->getMessage());
            $this->json(['ok' => false, 'message' => 'Could not create the administrator account.'], 500);
        }

        $this->json(['ok' => true, 'message' => 'Administrator account created.']);
    }

    public function apiStoreSettings(Request $request): void
    {
        if (Installer::isInstalled()) {
            $this->json(['ok' => false, 'message' => 'Sukli is already installed.'], 409);
        }

        $db = Session::get(self::SESSION_DB);
        $store = Session::get(self::SESSION_STORE);
        $storeId = Session::get(self::SESSION_STORE_ID);

        if (!$db || !$store || !$storeId) {
            $this->json(['ok' => false, 'message' => 'Missing setup data. Please start over.'], 400);
        }

        $result = $this->attemptConnection($db);
        if (!$result['ok']) {
            $this->json($result, 422);
        }

        try {
            $stmt = $result['pdo']->prepare('UPDATE stores SET name = ?, address = ?, phone = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$store['name'] ?: 'My Store', $store['address'] ?: null, $store['phone'] ?: null, $storeId]);
        } catch (PDOException $e) {
            error_log('[Sukli Installer] store-settings failed: ' . $e->getMessage());
            $this->json(['ok' => false, 'message' => 'Could not save store settings.'], 500);
        }

        $this->json(['ok' => true, 'message' => 'Store settings saved.']);
    }

    public function apiFinalize(Request $request): void
    {
        if (Installer::isInstalled()) {
            $this->json(['ok' => false, 'message' => 'Sukli is already installed.'], 409);
        }

        $db = Session::get(self::SESSION_DB);
        if (!$db) {
            $this->json(['ok' => false, 'message' => 'Missing setup data. Please start over.'], 400);
        }

        $configContent = $this->buildInstalledConfig($db);
        if (@file_put_contents(Installer::configPath(), $configContent, LOCK_EX) === false) {
            $this->json(['ok' => false, 'message' => 'Could not write the configuration file. Check that the config/ folder is writable, then try again.'], 500);
        }

        Installer::markInstalled();

        Session::forget(self::SESSION_DB);
        Session::forget(self::SESSION_ADMIN);
        Session::forget(self::SESSION_STORE);
        Session::forget(self::SESSION_ORG_ID);
        Session::forget(self::SESSION_STORE_ID);

        $this->json(['ok' => true, 'message' => 'Installation complete.']);
    }

    // -- Helpers ----------------------------------------------------------

    /** Returns true (and has already rendered a response) if install is locked. */
    private function alreadyInstalled(): bool
    {
        if (!Installer::isInstalled()) {
            return false;
        }
        $this->view('install/already_installed', ['pageTitle' => 'Already Installed'], 'layouts/install');
        return true;
    }

    /** @return array{ok:bool,message:string,pdo?:PDO} */
    private function attemptConnection(array $db): array
    {
        if (($db['database'] ?? '') === '' || ($db['username'] ?? '') === '') {
            return ['ok' => false, 'message' => 'Please fill in the database name and username.'];
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']);
        try {
            $pdo = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            return ['ok' => true, 'message' => 'Connected successfully.', 'pdo' => $pdo];
        } catch (PDOException $e) {
            error_log('[Sukli Installer] DB connection failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Unable to connect to the database. Please check your database credentials and try again.'];
        }
    }

    private function runSqlFile(PDO $pdo, string $path): void
    {
        $cleanedLines = array_filter(
            explode("\n", file_get_contents($path)),
            fn (string $line): bool => !str_starts_with(trim($line), '--')
        );
        $sql = implode("\n", $cleanedLines);

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
    }

    /**
     * Generates config/installed.php — a plain PHP file returning an array,
     * not a .env text file. See Installer::configPath() for why: it must
     * stay safe (never leak the DB password) even if a host's .htaccess
     * protection isn't actually in effect, since this file now lives
     * inside the same directory tree a web server serves directly.
     * var_export() is used for every value so nothing in a password (quotes,
     * backslashes, etc.) can break out of the generated PHP source.
     */
    private function buildInstalledConfig(array $db): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $appUrl = $scheme . '://' . $host;

        $config = [
            'db' => [
                'host' => $db['host'],
                'port' => $db['port'],
                'database' => $db['database'],
                'username' => $db['username'],
                'password' => $db['password'],
            ],
            'app' => [
                'name' => 'Sukli',
                'env' => 'production',
                'debug' => false,
                'url' => $appUrl,
            ],
        ];

        return "<?php\n\ndeclare(strict_types=1);\n\n// Generated by the Sukli installer — do not edit by hand.\n// Re-running the installer is blocked once storage/installed.lock exists;\n// to change these values, edit this file directly or delete it and the\n// lock file to reinstall (this does not touch your existing database).\nreturn "
            . var_export($config, true) . ";\n";
    }
}
