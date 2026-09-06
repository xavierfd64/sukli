<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Controller;
use Sukli\Core\Request;
use Sukli\Core\Session;
use Sukli\Services\AuditService;
use Sukli\Services\UpdateService;

/**
 * Platform Admin -> System Update. Upload a ZIP, run it through validation
 * (manifest, version, PHP compatibility, safe file paths) and — only for a
 * package explicitly typed "test" — a quarantined extract-and-verify pass.
 * There is deliberately no code path anywhere in this controller or
 * UpdateService that copies a file into the live application; see
 * UpdateService::validateAndProcessPackage() for why.
 */
class UpdateController extends Controller
{
    private const MAX_ZIP_BYTES = 10 * 1024 * 1024;

    public function index(Request $request): void
    {
        UpdateService::cleanupOldArtifacts();

        $this->view('platform-admin/system-update', [
            'pageTitle' => 'System Update',
            'currentVersion' => app_version(),
            'phpVersion' => PHP_VERSION,
            'result' => null,
            'uploadedName' => null,
        ], 'layouts/platform-admin');
    }

    public function upload(Request $request): void
    {
        $file = $request->file('package');

        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            Session::flash('error', 'Choose a ZIP file to upload.');
            $this->redirect('/platform-admin/system-update');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'The upload failed. Please try again.');
            $this->redirect('/platform-admin/system-update');
        }
        if ($file['size'] > self::MAX_ZIP_BYTES) {
            Session::flash('error', 'Update package is too large (max 10MB).');
            $this->redirect('/platform-admin/system-update');
        }
        if (!str_ends_with(strtolower($file['name']), '.zip')) {
            Session::flash('error', 'Only .zip files are accepted.');
            $this->redirect('/platform-admin/system-update');
        }

        $uploadsDir = UpdateService::baseDir() . '/uploads';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }
        $dest = $uploadsDir . '/' . bin2hex(random_bytes(8)) . '.zip';
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            Session::flash('error', 'Failed to save the uploaded package.');
            $this->redirect('/platform-admin/system-update');
        }

        $result = UpdateService::validateAndProcessPackage($dest);

        AuditService::log(
            $result['ok'] ? 'system_update_test_passed' : 'system_update_test_failed',
            'platform_admin',
            'system_update',
            0,
            null,
            ['package' => $file['name'], 'manifest_version' => $result['manifest']['version'] ?? null]
        );

        $this->view('platform-admin/system-update', [
            'pageTitle' => 'System Update',
            'currentVersion' => app_version(),
            'phpVersion' => PHP_VERSION,
            'result' => $result,
            'uploadedName' => $file['name'],
        ], 'layouts/platform-admin');
    }
}
