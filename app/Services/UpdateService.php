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
}
