<?php

declare(strict_types=1);

namespace Sukli\Core;

/**
 * Minimal .env loader — no Composer dependency required so the project
 * stays deployable on plain shared hosting.
 */
class Env
{
    private static array $values = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (strlen($value) >= 2 && (
                ($value[0] === '"' && str_ends_with($value, '"')) ||
                ($value[0] === "'" && str_ends_with($value, "'"))
            )) {
                $value = substr($value, 1, -1);
            }
            self::$values[$key] = $value;
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$values[$key] ?? (getenv($key) !== false ? getenv($key) : $default);
    }

    /**
     * The installer-generated config (config/installed.php), if present.
     * Preferred over .env by config/app.php and config/database.php — see
     * Installer::configPath() for why it's a .php file rather than .env.
     *
     * @return array{db?: array, app?: array}
     */
    public static function installed(): array
    {
        static $config = null;
        if ($config === null) {
            $path = Installer::configPath();
            $config = is_file($path) ? (require $path) : [];
        }
        return is_array($config) ? $config : [];
    }
}
