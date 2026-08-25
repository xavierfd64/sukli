<?php

declare(strict_types=1);

namespace Sukli\Services;

use Sukli\Core\Database;

/**
 * A unique, stable identifier for this deployed copy of Sukli — generated
 * once and never changed. It exists so a future update/subscription check
 * can recognize "this installation" without sending anything sensitive:
 * it is random, carries no embedded data, and is entirely independent of
 * the database credentials, user passwords, and store/customer records
 * this same database happens to also hold.
 *
 * Stored in its own single-row `installation` table rather than a config
 * file, since config/ may be read-only after install completes on shared
 * hosting — the database connection the app already depends on is the one
 * write path guaranteed to be available.
 */
class InstallationIdentity
{
    /** Returns the installation ID, generating and persisting one on first call if none exists yet. */
    public static function id(): string
    {
        $row = Database::one("SELECT installation_id FROM installation WHERE id = 1");
        if ($row) {
            return $row['installation_id'];
        }

        $generated = self::generate();
        Database::execute(
            "INSERT INTO installation (id, installation_id) VALUES (1, ?) ON DUPLICATE KEY UPDATE installation_id = installation_id",
            [$generated]
        );

        // Re-read rather than trust $generated directly, in case a concurrent
        // request won the INSERT race — every caller must see the same ID.
        return Database::one("SELECT installation_id FROM installation WHERE id = 1")['installation_id'];
    }

    private static function generate(): string
    {
        return sprintf(
            'SUKLI-%s-%s',
            strtoupper(bin2hex(random_bytes(4))),
            strtoupper(bin2hex(random_bytes(4)))
        );
    }
}
