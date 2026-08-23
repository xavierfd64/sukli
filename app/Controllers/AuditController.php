<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;

class AuditController extends Controller
{
    public function index(Request $request): void
    {
        $storeId = Auth::storeId();
        $module = $request->trimmed('module');
        $userId = $request->input('user_id', '');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;

        $where = ['a.store_id = ?'];
        $params = [$storeId];
        if ($module !== '') {
            $where[] = 'a.module = ?';
            $params[] = $module;
        }
        if ($userId !== '') {
            $where[] = 'a.user_id = ?';
            $params[] = (int) $userId;
        }
        $whereSql = implode(' AND ', $where);
        $fromSql = 'audit_logs a LEFT JOIN users u ON u.id = a.user_id';

        $total = (int) Database::one("SELECT COUNT(*) AS cnt FROM {$fromSql} WHERE {$whereSql}", $params)['cnt'];
        $offset = ($page - 1) * $perPage;

        $logs = Database::all(
            "SELECT a.*, u.name AS user_name FROM {$fromSql}
             WHERE {$whereSql} ORDER BY a.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $modules = Database::all("SELECT DISTINCT module FROM audit_logs WHERE store_id = ? ORDER BY module", [$storeId]);
        $users = Database::all("SELECT id, name FROM users WHERE store_id = ? ORDER BY name", [$storeId]);

        $this->view('audit/index', [
            'pageTitle' => 'Audit Log',
            'logs' => $logs,
            'modules' => $modules,
            'users' => $users,
            'module' => $module,
            'userId' => $userId,
            'page' => $page,
            'totalPages' => (int) ceil($total / $perPage),
        ]);
    }
}
