<?php

declare(strict_types=1);

namespace Sukli\Services;

/**
 * The extension point for the future central update system at
 * sukli-app.gt.tc. This class deliberately makes no network calls today —
 * that server, the update dashboard, and subscription verification are not
 * built yet. Its job right now is only to define the shape of the two
 * things that future integration will need: what gets sent to the update
 * server, and what a check-for-update result looks like, so the rest of
 * the app (and a future Settings "Check for Updates" button) can be wired
 * to a stable interface without another architecture change later.
 *
 * Hard requirements this class exists to enforce, per the product spec:
 *  - The app must keep working with no internet connection, with the
 *    update server unreachable, or before the update feature exists at
 *    all — so nothing here runs automatically, and nothing elsewhere in
 *    the app depends on this class returning a real answer.
 *  - An update check payload must contain only: installation_id,
 *    current_version, auto_update_enabled, and subscription_status — never
 *    the database password, other sensitive config, user passwords, or
 *    customer transaction data. requestPayload() is the single place that
 *    assembles that payload, so nothing else needs to.
 *  - No arbitrary downloaded file may ever auto-overwrite the app. When a
 *    real update pipeline is built, checkForUpdate()'s result is where
 *    version, release ID, file checksum, signature, and compatibility
 *    fields belong — verified before anything is installed, with a
 *    rollback strategy — none of which exists yet.
 */
class UpdateService
{
    /**
     * What a future "check sukli-app.gt.tc for updates" request would send.
     * Contains no secrets: no DB password, no other sensitive config, no
     * user passwords, no customer or transaction data.
     *
     * @return array{installation_id:string, current_version:string, auto_update_enabled:bool, subscription_status:string}
     */
    public static function requestPayload(int $storeId): array
    {
        return [
            'installation_id' => InstallationIdentity::id(),
            'current_version' => app_version(),
            'auto_update_enabled' => SystemSettingsService::getBool($storeId, 'auto_update_enabled', false),
            // Subscription verification doesn't exist yet — this placeholder
            // reserves the field so a future subscription server integration
            // doesn't need to change this payload's shape, only this value.
            'subscription_status' => 'not_applicable',
        ];
    }

    /**
     * Stubbed result of a future update check — makes no network request.
     * A future implementation would POST requestPayload() to
     * sukli-app.gt.tc, verify the response's signature/checksum, and
     * populate this same shape with the real answer; until then this
     * always reports "not checked" so callers can render a safe default
     * (e.g. no update banner) rather than erroring or blocking.
     *
     * @return array{checked:bool, available:bool, current_version:string, latest_version:?string, reason:string}
     */
    public static function checkForUpdate(): array
    {
        return [
            'checked' => false,
            'available' => false,
            'current_version' => app_version(),
            'latest_version' => null,
            'reason' => 'update_server_not_yet_implemented',
        ];
    }

    /**
     * Path prefixes an update manifest may never declare, whatever its
     * package type — checked before a single byte is extracted. This is the
     * one part of the update pipeline written to also hold for a real,
     * file-replacing update whenever that's eventually built: the manifest
     * validation rules should not need to change on that day, only the step
     * that acts on an already-validated manifest.
     */
    private const PROTECTED_PATH_PREFIXES = ['.env', 'config/', 'storage/', 'app/', 'assets/', 'database/', 'routes/', 'index.php', 'router.php', '.htaccess'];

    /**
     * The entire "Upload Update ZIP" pipeline, run synchronously in one pass:
     * open the zip, find and parse update.json, check declared PHP/app-version
     * compatibility, validate every declared file path is safe (no path
     * traversal, no protected app paths), then — ONLY for package type
     * "test" — extract strictly the declared files into a quarantine
     * directory under storage/updates/ and verify they landed correctly.
     *
     * Deliberately incomplete by design: there is no step here, or anywhere
     * else in this class, that copies a file into the live application. A
     * package validated as type "test" proves the whole pipeline (upload,
     * validation, compatibility, safe extraction, verification) works
     * without ever touching production files; other types validate the same
     * way but are reported as "not yet supported for apply" rather than
     * ever being applied, so uploading a non-test package can never do more
     * than a test one can.
     *
     * @return array{ok:bool, steps:list<array{label:string,ok:bool,detail:string}>, manifest:?array, quarantine_dir:?string}
     */
    public static function validateAndProcessPackage(string $zipPath): array
    {
        $steps = [];
        // A regular closure with an explicit by-reference `use` — an arrow
        // function's implicit capture is by VALUE at the point it's created,
        // which would freeze $steps at [] forever and drop every step
        // already recorded before whichever check fails.
        $fail = static function (string $label, string $detail) use (&$steps): array {
            $steps[] = ['label' => $label, 'ok' => false, 'detail' => $detail];
            return ['ok' => false, 'steps' => $steps, 'manifest' => null, 'quarantine_dir' => null];
        };

        $steps[] = ['label' => 'ZIP uploaded', 'ok' => true, 'detail' => basename($zipPath) . ' (' . self::humanBytes(filesize($zipPath) ?: 0) . ')'];

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return $fail('Package recognized', 'The uploaded file is not a valid ZIP archive.');
        }

        $manifestJson = $zip->getFromName('update.json');
        if ($manifestJson === false) {
            $zip->close();
            return $fail('Package recognized', 'No update.json manifest found at the root of the ZIP.');
        }
        $steps[] = ['label' => 'Package recognized', 'ok' => true, 'detail' => 'update.json found at package root.'];

        $manifest = json_decode($manifestJson, true);
        $required = ['package_name', 'version', 'type', 'min_php_version', 'files'];
        if (!is_array($manifest) || array_diff($required, array_keys($manifest)) !== [] || !is_array($manifest['files'])) {
            $zip->close();
            return $fail('Manifest valid', 'update.json is missing required fields (' . implode(', ', $required) . ').');
        }
        $steps[] = ['label' => 'Manifest valid', 'ok' => true, 'detail' => 'All required fields present.'];
        $steps[] = ['label' => 'Version detected', 'ok' => true, 'detail' => (string) $manifest['version'] . ' (' . (string) $manifest['type'] . ' package)'];

        $phpOk = version_compare(PHP_VERSION, (string) $manifest['min_php_version'], '>=');
        if (!$phpOk) {
            $zip->close();
            return $fail('PHP compatibility verified', 'This package requires PHP ' . (string) $manifest['min_php_version'] . '+, server is running ' . PHP_VERSION . '.');
        }
        $steps[] = ['label' => 'PHP compatibility verified', 'ok' => true, 'detail' => 'Server PHP ' . PHP_VERSION . ' satisfies minimum ' . (string) $manifest['min_php_version'] . '.'];

        // Every declared file must exist in the zip, resolve to a safe
        // relative path, and stay outside every protected app path — all
        // checked before anything is extracted.
        foreach ($manifest['files'] as $declared) {
            if (!is_string($declared) || $declared === '') {
                $zip->close();
                return $fail('Package structure verified', 'Manifest lists an invalid file entry.');
            }
            if (str_contains($declared, '..') || str_starts_with($declared, '/') || str_starts_with($declared, '\\') || preg_match('#^[A-Za-z]:#', $declared)) {
                $zip->close();
                return $fail('Package structure verified', 'Rejected unsafe path in manifest: "' . $declared . '" (path traversal or absolute path).');
            }
            foreach (self::PROTECTED_PATH_PREFIXES as $protected) {
                if ($declared === $protected || str_starts_with($declared, $protected)) {
                    $zip->close();
                    return $fail('Package structure verified', 'Rejected protected path in manifest: "' . $declared . '".');
                }
            }
            if ($zip->locateName($declared) === false) {
                $zip->close();
                return $fail('Package structure verified', 'Manifest declares "' . $declared . '" but it is not present in the ZIP.');
            }
        }
        // Reject the reverse case too: any entry inside the ZIP that the
        // manifest never declared — an update package may not smuggle in
        // undeclared files.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false || $entry === 'update.json' || str_ends_with($entry, '/')) {
                continue; // directory entries and the manifest itself are fine
            }
            if (!in_array($entry, $manifest['files'], true)) {
                $zip->close();
                return $fail('Package structure verified', 'ZIP contains an undeclared file: "' . $entry . '".');
            }
        }
        $steps[] = ['label' => 'Package structure verified', 'ok' => true, 'detail' => count($manifest['files']) . ' declared file(s), all present and no undeclared extras.'];

        // "Backup process verified" — a real backup of the whole app is
        // deliberately out of scope for this dry-run pipeline (nothing here
        // ever needs to restore anything, since nothing here is ever
        // applied). What this proves instead: the exact mechanism a real
        // backup step would use — writing to, then reading back from,
        // storage/ — actually works on this hosting account.
        $backupProbe = self::baseDir() . '/.backup-probe-' . bin2hex(random_bytes(6));
        $probeWrite = @file_put_contents($backupProbe, 'ok');
        $probeRead = $probeWrite !== false ? @file_get_contents($backupProbe) : false;
        @unlink($backupProbe);
        if ($probeWrite === false || $probeRead !== 'ok') {
            return $fail('Backup process verified', 'storage/updates/ is not writable — a real update could not safely back up first.');
        }
        $steps[] = ['label' => 'Backup process verified', 'ok' => true, 'detail' => 'Write/read to storage/ confirmed (no application files were backed up or touched — none of this pipeline applies changes yet).'];

        if ($manifest['type'] !== 'test') {
            $zip->close();
            $steps[] = ['label' => 'Test update processed', 'ok' => false, 'detail' => 'Package type "' . (string) $manifest['type'] . '" is not yet supported for apply — only "test" packages can be processed by this build. No files were changed.'];
            return ['ok' => false, 'steps' => $steps, 'manifest' => $manifest, 'quarantine_dir' => null];
        }

        $quarantineDir = self::baseDir() . '/quarantine/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        if (!mkdir($quarantineDir, 0755, true) && !is_dir($quarantineDir)) {
            $zip->close();
            return $fail('Test update processed', 'Could not create the quarantine folder to extract into.');
        }
        foreach ($manifest['files'] as $declared) {
            if (!$zip->extractTo($quarantineDir, $declared)) {
                $zip->close();
                return $fail('Test update processed', 'Failed to extract "' . $declared . '" into the quarantine folder.');
            }
        }
        $zip->close();
        $steps[] = ['label' => 'Test update processed', 'ok' => true, 'detail' => 'Declared files extracted to a quarantine folder under storage/updates/ — never into the live application.'];

        foreach ($manifest['files'] as $declared) {
            $extracted = $quarantineDir . '/' . $declared;
            if (!is_file($extracted) || !is_readable($extracted)) {
                return $fail('Post-update verification passed', 'Extracted file "' . $declared . '" is missing or unreadable after extraction.');
            }
        }
        $steps[] = ['label' => 'Post-update verification passed', 'ok' => true, 'detail' => 'Every declared file verified present and readable in quarantine.'];

        return ['ok' => true, 'steps' => $steps, 'manifest' => $manifest, 'quarantine_dir' => $quarantineDir];
    }

    /** Deletes everything under storage/updates/quarantine/ and storage/updates/uploads/ older than $olderThanMinutes — shared hosting has no cron, so this runs opportunistically each time the System Update page loads rather than on a schedule. */
    public static function cleanupOldArtifacts(int $olderThanMinutes = 60): void
    {
        foreach (['quarantine', 'uploads'] as $sub) {
            $dir = self::baseDir() . '/' . $sub;
            if (!is_dir($dir)) {
                continue;
            }
            foreach (scandir($dir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $dir . '/' . $entry;
                if ((time() - (int) filemtime($path)) > $olderThanMinutes * 60) {
                    is_dir($path) ? self::deleteDirectory($path) : @unlink($path);
                }
            }
        }
    }

    public static function baseDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/updates';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private static function deleteDirectory(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? self::deleteDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private static function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / (1024 * 1024), 2) . ' MB';
    }
}
