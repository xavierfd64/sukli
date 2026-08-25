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
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            throw new \RuntimeException('Failed to save the uploaded file.');
        }

        return $subdir . '/' . $filename;
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
