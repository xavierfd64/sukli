<?php

declare(strict_types=1);

namespace Sukli\Services;

/**
 * Validated file storage for user uploads (expense receipts, product
 * images, business logo) — saved under storage/uploads/, which the
 * project's flat-deployment .htaccess already serves directly like any
 * other static file, so a stored path is web-reachable via url().
 */
class UploadService
{
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    private const MAX_BYTES = 5 * 1024 * 1024;

    /** @param array $file The $_FILES-style array from Request::file(). @return string|null Relative path under storage/uploads/, or null if no file was provided. */
    public static function store(?array $file, string $subdir): ?string
    {
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('The upload failed. Please try again.');
        }
        if ($file['size'] > self::MAX_BYTES) {
            throw new \RuntimeException('File is too large (max 5MB).');
        }

        $mime = (string) mime_content_type($file['tmp_name']);
        if (!isset(self::ALLOWED_MIME[$mime])) {
            throw new \RuntimeException('Unsupported file type. Use JPG, PNG, WEBP or PDF.');
        }

        $subdir = trim($subdir, '/');
        $dir = self::baseDir() . '/' . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create the upload folder.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . self::ALLOWED_MIME[$mime];
        $destination = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException('Failed to save the uploaded file.');
        }
        // Shared hosting sometimes leaves moved files at a stricter mode
        // than the web server's user can read (e.g. 600) depending on the
        // PHP execution mode (suPHP/PHP-FPM) — force a world-readable mode
        // so the file is actually servable, without making it writable.
        @chmod($destination, 0644);

        if (!is_readable($destination)) {
            throw new \RuntimeException('The file was saved but is not readable — check storage/uploads permissions.');
        }

        return $subdir . '/' . $filename;
    }

    /** True if the given relative path both is set and actually exists on disk — lets views fall back to a placeholder instead of a broken image when a DB path has gone stale. */
    public static function exists(?string $relativePath): bool
    {
        return $relativePath !== null && $relativePath !== '' && is_file(self::baseDir() . '/' . $relativePath);
    }

    public static function delete(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }
        $full = self::baseDir() . '/' . $relativePath;
        if (is_file($full)) {
            @unlink($full);
        }
    }

    public static function url(?string $relativePath): ?string
    {
        return $relativePath ? url('storage/uploads/' . $relativePath) : null;
    }

    private static function baseDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/uploads';
    }
}
