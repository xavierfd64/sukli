<?php

declare(strict_types=1);

namespace Sukli\Core;

class Request
{
    private array $query;
    private array $body;
    private array $server;
    public array $params = [];

    public function __construct()
    {
        $this->query = $_GET;
        $this->body = $_POST;
        $this->server = $_SERVER;
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * The subfolder Sukli is installed under, e.g. "/sukli" when reached at
     * https://example.com/sukli/, or "" at a domain/subdomain root. Derived
     * from SCRIPT_NAME (where index.php actually lives) rather than stored
     * anywhere, so it self-corrects if the install is ever moved — the same
     * value both route matching (path()) and outgoing URLs (url() in
     * helpers.php) rely on to stay in sync automatically.
     */
    public static function basePath(): string
    {
        static $base = null;
        if ($base !== null) {
            return $base;
        }
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($script));
        return $base = ($dir === '/' || $dir === '.' || $dir === '') ? '' : rtrim($dir, '/');
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $base = self::basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
            if ($path === '' || $path[0] !== '/') {
                $path = '/' . $path;
            }
        }

        return rtrim($path, '/') === '' ? '/' : rtrim($path, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function trimmed(string $key, string $default = ''): string
    {
        return trim((string) $this->input($key, $default));
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function ip(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($this->server[$key])) {
                $parts = explode(',', $this->server[$key]);
                return trim($parts[0]);
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function file(string $key): ?array
    {
        if (empty($_FILES[$key]) || ($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $_FILES[$key];
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }
}
