<?php

declare(strict_types=1);

namespace Sukli\Core;

/**
 * Installation-state helper used by the /install wizard and the front
 * controller's first-visit redirect. Purely additive to the existing
 * system — nothing else in the app depends on this class, and nothing
 * about how the app runs once installed changes because of it.
 *
 * "Installed" is determined solely by the presence of a lock file, never
 * by inspecting the database — that keeps the check fast (no DB round trip
 * on every request) and matches the WordPress-style "config file/lock
 * exists = installed" convention this wizard is modeled on.
 */
class Installer
{
    public static function lockPath(): string
    {
        return __DIR__ . '/../../storage/installed.lock';
    }

    /**
     * Where the installer writes its generated configuration. This is a
     * PHP file (not a plain .env text file) on purpose: config/, app/,
     * database/, and storage/ all now sit inside the same directory a web
     * server serves directly (see README — the deployment structure is
     * flat, no /public docroot required), so anything the installer writes
     * must stay safe even if a host's .htaccess overrides don't apply.
     * A .php file is always executed rather than served as text by any
     * host that can run this app at all, so a bare `<?php return [...]`
     * file never leaks its contents even with zero .htaccess protection —
     * whereas a plain-text .env would be directly downloadable.
     */
    public static function configPath(): string
    {
        return __DIR__ . '/../../config/installed.php';
    }

    public static function isInstalled(): bool
    {
        return is_file(self::lockPath());
    }

    public static function markInstalled(): void
    {
        $payload = "installed_at=" . date('c') . "\n";
        file_put_contents(self::lockPath(), $payload, LOCK_EX);
    }
}
