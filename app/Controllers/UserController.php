<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Core\Session;
use Sukli\Services\AuditService;
use Sukli\Services\BranchAccessService;
use Sukli\Services\SubscriptionService;

class UserController extends Controller
{
    public function index(Request $request): void
    {
        $storeId = Auth::storeId();
        $organizationId = (int) Auth::organizationId();
        $isOwner = Auth::hasRole(['owner']);

        // An Owner runs the whole business, not one branch — they see every
        // user across the organization (with each one's home branch shown)
        // regardless of which branch they're currently switched into. A
        // Branch Manager/Cashier only ever sees their own branch's users.
        if ($isOwner) {
            $users = Database::all(
                "SELECT u.*, r.name AS role_name, s.name AS branch_name FROM users u
                 JOIN roles r ON r.id = u.role_id
                 LEFT JOIN stores s ON s.id = u.store_id
                 WHERE u.organization_id = ? ORDER BY u.name",
                [$organizationId]
            );
            $branches = Database::all(
                "SELECT id, name FROM stores WHERE organization_id = ? AND status = 'active' ORDER BY is_main_branch DESC, name",
                [$organizationId]
            );
        } else {
            $users = Database::all(
                "SELECT u.*, r.name AS role_name, s.name AS branch_name FROM users u
                 JOIN roles r ON r.id = u.role_id
                 LEFT JOIN stores s ON s.id = u.store_id
                 WHERE u.store_id = ? ORDER BY u.name",
                [$storeId]
            );
            $branches = [];
        }

        $roles = Database::all(
            "SELECT id, role_key, name FROM roles WHERE store_id IS NULL OR store_id = ? ORDER BY is_system DESC, name",
            [$storeId]
        );

        $this->view('users/index', [
            'pageTitle' => 'Users',
            'users' => $users,
            'roles' => $roles,
            'branches' => $branches,
            'isOwner' => $isOwner,
        ]);
    }

    public function store(Request $request): void
    {
        $storeId = Auth::storeId();
        $organizationId = (int) Auth::organizationId();
        $isOwner = Auth::hasRole(['owner']);
        $name = $request->trimmed('name');
        $username = $request->trimmed('username');
        $password = (string) $request->input('password', '');
        $roleId = (int) $request->input('role_id', 0);

        if ($name === '' || $username === '' || strlen($password) < 8 || $roleId <= 0) {
            Session::flash('error', 'Please fill all fields. Password must be at least 8 characters.');
            $this->back('/users');
        }

        if (!SubscriptionService::withinLimit($organizationId, 'users')) {
            Session::flash('error', 'Your plan\'s user limit has been reached. Upgrade your subscription to add more users.');
            $this->back('/users');
        }

        $exists = Database::one("SELECT id FROM users WHERE username = ?", [$username]);
        if ($exists) {
            Session::flash('error', 'That username is already taken.');
            $this->back('/users');
        }

        // Owners may assign a new user to any of their organization's
        // branches; anyone else can only add users to the branch they're
        // currently in. Never trust a submitted branch_id past this check.
        $homeStoreId = $storeId;
        $requestedBranchId = (int) $request->input('branch_id', 0);
        if ($isOwner && $requestedBranchId > 0) {
            $validBranch = Database::one("SELECT id FROM stores WHERE id = ? AND organization_id = ? AND status = 'active'", [$requestedBranchId, $organizationId]);
            if ($validBranch) {
                $homeStoreId = $requestedBranchId;
            }
        }

        Database::execute(
            "INSERT INTO users (organization_id, store_id, role_id, name, username, email, password_hash, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active')",
            [$organizationId, $homeStoreId, $roleId, $name, $username, $request->trimmed('email') ?: null,
             password_hash($password, PASSWORD_DEFAULT)]
        );
        $id = (int) Database::lastInsertId();
        BranchAccessService::grant($id, $homeStoreId);

        AuditService::log('create', 'users', 'user', $id, null, ['username' => $username, 'role_id' => $roleId]);
        Session::flash('success', 'User added.');
        $this->back('/users');
    }

    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $storeId = Auth::storeId();
        $organizationId = (int) Auth::organizationId();
        $isOwner = Auth::hasRole(['owner']);

        // An Owner can edit anyone in their organization; anyone else only
        // their own branch's users — never trust the URL's {id} past this.
        $existing = $isOwner
            ? Database::one("SELECT * FROM users WHERE id = ? AND organization_id = ?", [$id, $organizationId])
            : Database::one("SELECT * FROM users WHERE id = ? AND store_id = ?", [$id, $storeId]);
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
                "UPDATE users SET name=?, role_id=?, email=?, password_hash=?, updated_at=NOW() WHERE id = ?",
                [$name, $roleId, $email, password_hash($password, PASSWORD_DEFAULT), $id]
            );
        } else {
            Database::execute(
                "UPDATE users SET name=?, role_id=?, email=?, updated_at=NOW() WHERE id = ?",
                [$name, $roleId, $email, $id]
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
        $organizationId = (int) Auth::organizationId();
        $isOwner = Auth::hasRole(['owner']);

        if ($id === Auth::id()) {
            Session::flash('error', 'You cannot deactivate your own account.');
            $this->back('/users');
        }

        $target = $isOwner
            ? Database::one("SELECT status, role_id FROM users WHERE id = ? AND organization_id = ?", [$id, $organizationId])
            : Database::one("SELECT status, role_id FROM users WHERE id = ? AND store_id = ?", [$id, $storeId]);
        if (!$target) {
            Session::flash('error', 'User not found.');
            $this->back('/users');
        }

        if ($target['status'] === 'active') {
            $ownerRole = Database::one("SELECT id FROM roles WHERE role_key = 'owner'");
            if ((int) $target['role_id'] === (int) $ownerRole['id']) {
                // Owner is an organization-wide role, not a per-branch one —
                // count active Owners across the whole organization, not
                // just this branch, so this check can't be fooled by owners
                // whose home branch happens to differ.
                $activeOwners = Database::one(
                    "SELECT COUNT(*) AS cnt FROM users WHERE organization_id = ? AND role_id = ? AND status = 'active'",
                    [$organizationId, $ownerRole['id']]
                );
                if ((int) $activeOwners['cnt'] <= 1) {
                    Session::flash('error', 'At least one active Owner account is required.');
                    $this->back('/users');
                }
            }
        }

        $newStatus = $target['status'] === 'active' ? 'inactive' : 'active';
        Database::execute("UPDATE users SET status = ? WHERE id = ?", [$newStatus, $id]);

        AuditService::log($newStatus === 'inactive' ? 'deactivate' : 'reactivate', 'users', 'user', $id);
        Session::flash('success', 'User ' . ($newStatus === 'inactive' ? 'deactivated' : 'reactivated') . '.');
        $this->back('/users');
    }
}
