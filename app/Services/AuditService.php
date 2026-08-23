<?php

declare(strict_types=1);

namespace Sukli\Services;

use Sukli\Core\Auth;
use Sukli\Core\Database;
use Sukli\Core\Request;

/**
 * Writes append-only audit trail rows. Never expose an update/delete path
 * for audit_logs from the application layer — only DB admins should touch
 * that table directly, and only for retention/GDPR-style cleanup.
 */
class AuditService
{
    public static function log(
        string $action,
        string $module,
        ?string $relatedType = null,
        int|string|null $relatedId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $request = new Request();

        Database::execute(
            "INSERT INTO audit_logs
                (organization_id, store_id, user_id, action, module, related_type, related_id, old_values, new_values, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                Auth::organizationId(),
                Auth::storeId(),
                Auth::id(),
                $action,
                $module,
                $relatedType,
                $relatedId,
                $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                $request->ip(),
                $request->userAgent(),
            ]
        );
    }
}
