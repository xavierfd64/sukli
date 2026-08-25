<?php

declare(strict_types=1);

namespace Sukli\Services;

use DateTimeZone;
use Sukli\Core\Auth;
use Sukli\Core\Database;

/**
 * Applies the logged-in store's chosen timezone consistently to both PHP
 * and MySQL for the duration of the request, so date_default_timezone
 * (used by PHP's date()/DateTime for display and range calculations) and
 * MySQL's NOW()/CURDATE()/CURRENT_TIMESTAMP (used by created_at/updated_at
 * defaults and DATE(...) filters) always agree on the same wall-clock time.
 *
 * MySQL's `SET time_zone` is given a numeric UTC offset (e.g. "+08:00")
 * rather than a named zone like "Asia/Manila" — named zones require the
 * mysql.time_zone_name tables to be populated, which many shared-hosting
 * MySQL installs don't have. An offset always works, with no server-side
 * setup required.
 */
class TimezoneService
{
    private static ?string $applied = null;

    public static function apply(): void
    {
        $storeId = Auth::storeId();
        if (!$storeId) {
            return;
        }

        $timezone = self::storeTimezone((int) $storeId);
        if ($timezone === self::$applied) {
            return;
        }

        date_default_timezone_set($timezone);

        $offset = self::offsetFor($timezone);
        Database::execute('SET time_zone = ?', [$offset]);

        self::$applied = $timezone;
    }

    public static function storeTimezone(int $storeId): string
    {
        $row = Database::one('SELECT timezone FROM stores WHERE id = ?', [$storeId]);
        $timezone = $row['timezone'] ?? 'Asia/Manila';
        return self::isValid($timezone) ? $timezone : 'Asia/Manila';
    }

    public static function isValid(string $timezone): bool
    {
        return in_array($timezone, timezone_identifiers_list(), true);
    }

    public static function offsetFor(string $timezone): string
    {
        try {
            $tz = new DateTimeZone($timezone);
            $offsetSeconds = $tz->getOffset(new \DateTime('now', $tz));
        } catch (\Exception) {
            $offsetSeconds = 8 * 3600; // Asia/Manila fallback
        }

        $sign = $offsetSeconds < 0 ? '-' : '+';
        $abs = abs($offsetSeconds);
        return sprintf('%s%02d:%02d', $sign, intdiv($abs, 3600), intdiv($abs % 3600, 60));
    }

    /** @return string[] Full list of valid PHP/IANA timezone identifiers. */
    public static function all(): array
    {
        return timezone_identifiers_list();
    }
}
