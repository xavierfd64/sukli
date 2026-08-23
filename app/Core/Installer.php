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

    public static function envPath(): string
    {
        return __DIR__ . '/../../.env';
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
