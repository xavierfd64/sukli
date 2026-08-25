<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Core\Session;
use Sukli\Services\AuditService;

class UserController extends Controller
{
    public function index(Request $request): void
    {
        $storeId = Auth::storeId();
        $users = Database::all(
            "SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.store_id = ? ORDER BY u.name",
            [$storeId]
        );
        $roles = Database::all(
            "SELECT id, role_key, name FROM roles WHERE store_id IS NULL OR store_id = ? ORDER BY is_system DESC, name",
            [$storeId]
        );

        $this->view('users/index', [
            'pageTitle' => 'Users',
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): void
    {
        $storeId = Auth::storeId();
        $organizationId = Auth::organizationId();
        $name = $request->trimmed('name');
        $username = $request->trimmed('username');
        $password = (string) $request->input('password', '');
        $roleId = (int) $request->input('role_id', 0);

        if ($name === '' || $username === '' || strlen($password) < 8 || $roleId <= 0) {
            Session::flash('error', 'Please fill all fields. Password must be at least 8 characters.');
            $this->back('/users');
        }

        $exists = Database::one("SELECT id FROM users WHERE username = ?", [$username]);
        if ($exists) {
            Session::flash('error', 'That username is already taken.');
            $this->back('/users');
        }

        Database::execute(
            "INSERT INTO users (organization_id, store_id, role_id, name, username, email, password_hash, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active')",
            [$organizationId, $storeId, $roleId, $name, $username, $request->trimmed('email') ?: null,
             password_hash($password, PASSWORD_DEFAULT)]
        );
        $id = (int) Database::lastInsertId();
        Database::execute(
            "INSERT INTO user_store_access (user_id, store_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE store_id = VALUES(store_id)",
            [$id, $storeId]
        );

        AuditService::log('create', 'users', 'user', $id, null, ['username' => $username, 'role_id' => $roleId]);
        Session::flash('success', 'User added.');
        $this->back('/users');
    }

    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();
        $existing = Database::one("SELECT * FROM users WHERE id = ? AND store_id = ?", [$id, $storeId]);
        if (!$existing) {
            Session::flash('error', 'User not found.');
            $this->back('/users');
        }

        $name = $request->trimmed('name');
        $roleId = (int) $request->input('role_id', 0);
        $email = $request->trimmed('email') ?: null;
        $password = (string) $request->input('password', '');

        if ($name === '' || $roleId <= 0) {
            Session::flash('error', 'Please fill all required fields.');
            $this->back('/users');
        }

        if ($password !== '') {
            if (strlen($password) < 8) {
                Session::flash('error', 'Password must be at least 8 characters.');
                $this->back('/users');
            }
            Database::execute(
                "UPDATE users SET name=?, role_id=?, email=?, password_hash=?, updated_at=NOW() WHERE id = ? AND store_id = ?",
                [$name, $roleId, $email, password_hash($password, PASSWORD_DEFAULT), $id, $storeId]
            );
        } else {
            Database::execute(
                "UPDATE users SET name=?, role_id=?, email=?, updated_at=NOW() WHERE id = ? AND store_id = ?",
                [$name, $roleId, $email, $id, $storeId]
            );
        }

        AuditService::log('update', 'users', 'user', $id);
        Session::flash('success', 'User updated.');
        $this->back('/users');
    }

    public function deactivate(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();

        if ($id === Auth::id()) {
            Session::flash('error', 'You cannot deactivate your own account.');
            $this->back('/users');
        }

        $target = Database::one("SELECT status, role_id FROM users WHERE id = ? AND store_id = ?", [$id, $storeId]);
        if (!$target) {
            Session::flash('error', 'User not found.');
            $this->back('/users');
        }

        if ($target['status'] === 'active') {
            $ownerRole = Database::one("SELECT id FROM roles WHERE role_key = 'owner'");
            if ((int) $target['role_id'] === (int) $ownerRole['id']) {
                $activeOwners = Database::one(
                    "SELECT COUNT(*) AS cnt FROM users WHERE store_id = ? AND role_id = ? AND status = 'active'",
                    [$storeId, $ownerRole['id']]
                );
                if ((int) $activeOwners['cnt'] <= 1) {
                    Session::flash('error', 'At least one active Owner account is required.');
                    $this->back('/users');
                }
            }
        }

        $newStatus = $target['status'] === 'active' ? 'inactive' : 'active';
        Database::execute("UPDATE users SET status = ? WHERE id = ? AND store_id = ?", [$newStatus, $id, $storeId]);

        AuditService::log($newStatus === 'inactive' ? 'deactivate' : 'reactivate', 'users', 'user', $id);
        Session::flash('success', 'User ' . ($newStatus === 'inactive' ? 'deactivated' : 'reactivated') . '.');
        $this->back('/users');
    }
}
