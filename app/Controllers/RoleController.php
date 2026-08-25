<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Core\Session;
use Sukli\Services\AuditService;
use Sukli\Services\PermissionService;

class RoleController extends Controller
{
    /** Reserved role_keys used elsewhere in the app for system-role logic (e.g. the "at least one Owner" safeguard). */
    private const RESERVED_KEYS = ['owner', 'manager', 'cashier'];

    public function index(Request $request): void
    {
        $storeId = Auth::storeId();
        $roles = Database::all(
            "SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id AND u.store_id = ?) AS user_count
             FROM roles r WHERE r.store_id IS NULL OR r.store_id = ?
             ORDER BY r.is_system DESC, r.name",
            [$storeId, $storeId]
        );

        $this->view('roles/index', [
            'pageTitle' => 'Roles & Permissions',
            'roles' => $roles,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('roles/form', [
            'pageTitle' => 'Add Role',
            'role' => null,
            'catalog' => PermissionService::catalog(),
            'allowed' => [],
        ]);
    }

    public function store(Request $request): void
    {
        $storeId = (int) Auth::storeId();
        $name = $request->trimmed('name');
        if (!$name) {
            Session::flash('error', 'Enter a role name.');
            $this->back('/roles/create');
        }

        $roleKey = self::slugify($name);
        if (in_array($roleKey, self::RESERVED_KEYS, true) || $roleKey === '') {
            $roleKey = 'custom_' . substr(bin2hex(random_bytes(4)), 0, 8);
        }

        try {
            Database::execute(
                "INSERT INTO roles (store_id, role_key, name, description, is_system) VALUES (?, ?, ?, ?, 0)",
                [$storeId, $roleKey, $name, $request->trimmed('description') ?: null]
            );
        } catch (\Throwable $e) {
            Session::flash('error', 'A role with that name already exists.');
            $this->back('/roles/create');
        }
        $roleId = (int) Database::lastInsertId();

        $this->savePermissions($roleId, $request);

        AuditService::log('create', 'users', 'role', $roleId, null, ['name' => $name]);
        Session::flash('success', 'Role created.');
        $this->redirect('/roles');
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();
        $role = Database::one(
            "SELECT * FROM roles WHERE id = ? AND (store_id IS NULL OR store_id = ?)",
            [$id, $storeId]
        );
        if (!$role) {
            Session::flash('error', 'Role not found.');
            $this->redirect('/roles');
        }

        $this->view('roles/form', [
            'pageTitle' => 'Edit Role — ' . $role['name'],
            'role' => $role,
            'catalog' => PermissionService::catalog(),
            'allowed' => PermissionService::forRole($id),
        ]);
    }

    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();
        $role = Database::one(
            "SELECT * FROM roles WHERE id = ? AND (store_id IS NULL OR store_id = ?)",
            [$id, $storeId]
        );
        if (!$role) {
            Session::flash('error', 'Role not found.');
            $this->redirect('/roles');
        }

        if (!$role['is_system']) {
            $name = $request->trimmed('name');
            if (!$name) {
                Session::flash('error', 'Enter a role name.');
                $this->back('/roles/' . $id . '/edit');
            }
            Database::execute(
                "UPDATE roles SET name = ?, description = ? WHERE id = ?",
                [$name, $request->trimmed('description') ?: null, $id]
            );
        }

        $this->savePermissions($id, $request);

        AuditService::log('update', 'users', 'role', $id);
        Session::flash('success', 'Role permissions updated.');
        $this->redirect('/roles');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();
        $role = Database::one("SELECT * FROM roles WHERE id = ? AND store_id = ? AND is_system = 0", [$id, $storeId]);
        if (!$role) {
            Session::flash('error', 'Only custom roles can be deleted.');
            $this->back('/roles');
        }

        $inUse = Database::one("SELECT COUNT(*) AS cnt FROM users WHERE role_id = ?", [$id]);
        if ((int) $inUse['cnt'] > 0) {
            Session::flash('error', 'Cannot delete a role that is still assigned to users. Reassign them first.');
            $this->back('/roles');
        }

        Database::execute("DELETE FROM roles WHERE id = ?", [$id]);
        AuditService::log('delete', 'users', 'role', $id, $role);
        Session::flash('success', 'Role deleted.');
        $this->redirect('/roles');
    }

    private function savePermissions(int $roleId, Request $request): void
    {
        $submitted = $request->input('permissions', []);
        if (!is_array($submitted)) {
            $submitted = [];
        }

        $allowedByPermissionId = [];
        foreach (PermissionService::catalog() as $rows) {
            foreach ($rows as $row) {
                $allowedByPermissionId[(int) $row['id']] = isset($submitted[$row['id']]);
            }
        }

        PermissionService::setRolePermissions($roleId, $allowedByPermissionId);
    }

    private static function slugify(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $name) ?? '', '_'));
        return substr($slug, 0, 30);
    }
}
