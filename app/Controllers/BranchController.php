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

/**
 * Branch (multi-store) management for an organization, plus the switch
 * endpoint the topbar branch picker posts to. Every check here is
 * server-side against the authenticated user's own organization/access —
 * a submitted store_id is never trusted on its own (see BranchAccessService).
 */
class BranchController extends Controller
{
    public function index(Request $request): void
    {
        $organizationId = (int) Auth::organizationId();
        $branches = Database::all(
            "SELECT s.*, (SELECT COUNT(*) FROM users u WHERE u.store_id = s.id) AS user_count
             FROM stores s WHERE s.organization_id = ? ORDER BY s.is_main_branch DESC, s.name",
            [$organizationId]
        );

        $subscription = SubscriptionService::forOrganization($organizationId);
        $limit = SubscriptionService::limitFor($subscription, 'branches');
        $activeCount = SubscriptionService::usage($organizationId, 'branches');

        $this->view('branches/index', [
            'pageTitle' => 'Branches',
            'branches' => $branches,
            'limit' => $limit,
            'activeCount' => $activeCount,
            'canAddMore' => $limit === null || $activeCount < $limit,
        ]);
    }

    public function store(Request $request): void
    {
        $organizationId = (int) Auth::organizationId();

        if (!SubscriptionService::withinLimit($organizationId, 'branches')) {
            Session::flash('error', 'Your plan\'s branch limit has been reached. Upgrade your subscription to add more branches.');
            $this->back('/branches');
        }

        $name = $request->trimmed('name');
        if ($name === '') {
            Session::flash('error', 'Enter a branch name.');
            $this->back('/branches');
        }

        Database::execute(
            "INSERT INTO stores (organization_id, name, branch_code, address, phone, status)
             VALUES (?, ?, ?, ?, ?, 'active')",
            [$organizationId, $name, $request->trimmed('branch_code') ?: null, $request->trimmed('address') ?: null, $request->trimmed('phone') ?: null]
        );
        $id = (int) Database::lastInsertId();

        AuditService::log('create', 'branches', 'store', $id, null, ['name' => $name]);
        Session::flash('success', 'Branch added.');
        $this->back('/branches');
    }

    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $organizationId = (int) Auth::organizationId();

        $existing = Database::one("SELECT id, is_main_branch FROM stores WHERE id = ? AND organization_id = ?", [$id, $organizationId]);
        if (!$existing) {
            Session::flash('error', 'Branch not found.');
            $this->back('/branches');
        }

        $name = $request->trimmed('name');
        if ($name === '') {
            Session::flash('error', 'Enter a branch name.');
            $this->back('/branches');
        }

        Database::execute(
            "UPDATE stores SET name = ?, branch_code = ?, address = ?, phone = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?",
            [$name, $request->trimmed('branch_code') ?: null, $request->trimmed('address') ?: null, $request->trimmed('phone') ?: null, $id, $organizationId]
        );

        AuditService::log('update', 'branches', 'store', $id);
        Session::flash('success', 'Branch updated.');
        $this->back('/branches');
    }

    public function toggle(Request $request): void
    {
        $id = (int) $request->param('id');
        $organizationId = (int) Auth::organizationId();

        $branch = Database::one("SELECT id, name, status, is_main_branch FROM stores WHERE id = ? AND organization_id = ?", [$id, $organizationId]);
        if (!$branch) {
            Session::flash('error', 'Branch not found.');
            $this->back('/branches');
        }
        if ($branch['is_main_branch']) {
            Session::flash('error', 'The Main Branch cannot be disabled.');
            $this->back('/branches');
        }

        $newStatus = $branch['status'] === 'active' ? 'inactive' : 'active';
        Database::execute("UPDATE stores SET status = ? WHERE id = ? AND organization_id = ?", [$newStatus, $id, $organizationId]);

        // A disabled branch can't be anyone's active session — bump anyone
        // currently switched into it back to their home branch's next login.
        if ($newStatus === 'inactive' && (int) Auth::storeId() === $id) {
            Session::put('store_id', (int) Database::one("SELECT store_id FROM users WHERE id = ?", [Auth::id()])['store_id']);
        }

        AuditService::log($newStatus === 'inactive' ? 'disable' : 'enable', 'branches', 'store', $id);
        Session::flash('success', 'Branch ' . ($newStatus === 'inactive' ? 'disabled' : 'enabled') . '.');
        $this->back('/branches');
    }

    /** Switches the session's active branch — the ONLY thing that changes Auth::storeId() after login. */
    public function switchBranch(Request $request): void
    {
        $storeId = (int) $request->input('store_id', 0);
        $organizationId = (int) Auth::organizationId();
        $isOwner = Auth::hasRole(['owner']);

        if ($storeId > 0 && BranchAccessService::canAccess((int) Auth::id(), $organizationId, $storeId, $isOwner)) {
            Session::put('store_id', $storeId);
            AuditService::log('switch_branch', 'branches', 'store', $storeId);
        } else {
            Session::flash('error', 'You do not have access to that branch.');
        }

        $this->back('/dashboard');
    }
}
