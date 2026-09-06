<?php

declare(strict_types=1);

namespace Sukli\Services;

use Sukli\Core\Database;

/**
 * Single source of truth for the platform_settings key/value table (platform
 * name, theme color/font, trial length, ...) — cached per-request so every
 * page's <title> and theme override can read it without a repeat query.
 * Whoever writes here must call clearCache() (or rely on set()'s own cache
 * update) so a save is reflected immediately on the same request/redirect.
 */
class PlatformSettingsService
{
    private static ?array $cache = null;

    /** Controlled, network-free font stacks — deliberately not an arbitrary CSS/URL field (Platform Admin picks a key, never types a stack). */
    public const FONT_CHOICES = [
        'system' => ['label' => 'System Default', 'stack' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif'],
        'classic' => ['label' => 'Classic Serif', 'stack' => 'Georgia, "Times New Roman", Times, serif'],
        'rounded' => ['label' => 'Friendly', 'stack' => '"Trebuchet MS", Verdana, sans-serif'],
        'compact' => ['label' => 'Compact', 'stack' => 'Tahoma, Verdana, sans-serif'],
    ];
    public const DEFAULT_ACCENT = '#16a34a';
    public const DEFAULT_FONT = 'system';

    /**
     * Every page render calls get() for the <title>/theme override, including
     * ones that can happen before the app is fully installed (or if a
     * shared-hosting DB hiccups mid-request) — so a missing connection or a
     * not-yet-created platform_settings table must degrade to "use the
     * defaults", never a fatal error.
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            $rows = Database::all("SELECT setting_key, setting_value FROM platform_settings");
        } catch (\Throwable $e) {
            return self::$cache = [];
        }

        return self::$cache = array_column($rows, 'setting_value', 'setting_key');
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        Database::execute(
            "INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
            [$key, $value]
        );
        self::all(); // Ensure the cache is populated before we patch a single key into it.
        self::$cache[$key] = $value;
    }

    public static function delete(string $key): void
    {
        Database::execute("DELETE FROM platform_settings WHERE setting_key = ?", [$key]);
        self::all();
        unset(self::$cache[$key]);
    }
}
