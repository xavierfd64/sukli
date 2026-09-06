<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Core\Session;
use Sukli\Services\AuditService;
use Sukli\Services\PlatformSettingsService;
use Sukli\Services\SubscriptionService;
use Sukli\Services\UploadService;

/**
 * The entire /platform-admin area — gated by PlatformAdminMiddleware
 * (Auth::isPlatformAdmin(), not a per-tenant role/permission). Every query
 * here deliberately spans all organizations; nowhere else in the app does
 * that.
 */
class PlatformAdminController extends Controller
{
    public function dashboard(Request $request): void
    {
        SubscriptionService::reconcileAll();

        $counts = Database::one(
            "SELECT
                (SELECT COUNT(*) FROM organizations) AS total_orgs,
                (SELECT COUNT(*) FROM subscriptions WHERE status = 'active') AS active_orgs,
                (SELECT COUNT(*) FROM subscriptions WHERE status = 'trial') AS trial_orgs,
                (SELECT COUNT(*) FROM subscriptions WHERE status IN ('expired','suspended')) AS expired_orgs,
                (SELECT COUNT(*) FROM stores WHERE status = 'active') AS total_branches,
                (SELECT COUNT(*) FROM users WHERE status = 'active') AS total_users,
                (SELECT COUNT(*) FROM subscription_payments WHERE status = 'pending') AS pending_payments,
                (SELECT COALESCE(SUM(amount),0) FROM subscription_payments WHERE status = 'approved') AS revenue_total,
                (SELECT COALESCE(SUM(amount),0) FROM subscription_payments WHERE status = 'approved' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS revenue_this_month"
        );

        $recentOrgs = Database::all(
            "SELECT o.id, o.name, o.created_at, s.status AS subscription_status, p.name AS plan_name
             FROM organizations o
             LEFT JOIN subscriptions s ON s.organization_id = o.id
             LEFT JOIN subscription_plans p ON p.id = s.subscription_plan_id
             ORDER BY o.created_at DESC LIMIT 8"
        );

        $this->view('platform-admin/dashboard', [
            'pageTitle' => 'Platform Admin',
            'counts' => $counts,
            'recentOrgs' => $recentOrgs,
        ], 'layouts/platform-admin');
    }

    public function organizations(Request $request): void
    {
        SubscriptionService::reconcileAll();

        $orgs = Database::all(
            "SELECT o.id, o.name, o.slug, o.created_at, s.id AS subscription_id, s.status AS subscription_status, p.name AS plan_name,
                    (SELECT COUNT(*) FROM stores st WHERE st.organization_id = o.id AND st.status = 'active') AS branch_count,
                    (SELECT COUNT(*) FROM users u WHERE u.organization_id = o.id AND u.status = 'active') AS user_count,
                    (SELECT name FROM users u WHERE u.organization_id = o.id AND u.role_id = (SELECT id FROM roles WHERE role_key = 'owner') ORDER BY u.id LIMIT 1) AS owner_name,
                    (SELECT email FROM users u WHERE u.organization_id = o.id AND u.role_id = (SELECT id FROM roles WHERE role_key = 'owner') ORDER BY u.id LIMIT 1) AS owner_email
             FROM organizations o
             LEFT JOIN subscriptions s ON s.organization_id = o.id
             LEFT JOIN subscription_plans p ON p.id = s.subscription_plan_id
             ORDER BY o.created_at DESC"
        );

        $this->view('platform-admin/organizations', [
            'pageTitle' => 'Organizations',
            'orgs' => $orgs,
        ], 'layouts/platform-admin');
    }

    public function organizationDetail(Request $request): void
    {
        $id = (int) $request->param('id');
        $org = Database::one("SELECT * FROM organizations WHERE id = ?", [$id]);
        if (!$org) {
            Session::flash('error', 'Organization not found.');
            $this->redirect('/platform-admin/organizations');
        }

        $subscription = SubscriptionService::forOrganization($id);
        $branches = Database::all("SELECT * FROM stores WHERE organization_id = ? ORDER BY is_main_branch DESC, name", [$id]);
        $users = Database::all(
            "SELECT u.*, r.name AS role_name, s.name AS branch_name FROM users u
             JOIN roles r ON r.id = u.role_id LEFT JOIN stores s ON s.id = u.store_id
             WHERE u.organization_id = ? ORDER BY u.name",
            [$id]
        );
        $payments = Database::all(
            "SELECT sp.*, pl.name AS plan_name FROM subscription_payments sp
             JOIN subscription_plans pl ON pl.id = sp.subscription_plan_id
             WHERE sp.organization_id = ? ORDER BY sp.created_at DESC",
            [$id]
        );

        $this->view('platform-admin/organization-detail', [
            'pageTitle' => $org['name'],
            'org' => $org,
            'subscription' => $subscription,
            'branches' => $branches,
            'users' => $users,
            'payments' => $payments,
        ], 'layouts/platform-admin');
    }

    public function suspendOrganization(Request $request): void
    {
        $id = (int) $request->param('id');
        $sub = Database::one("SELECT id FROM subscriptions WHERE organization_id = ?", [$id]);
        if ($sub) {
            SubscriptionService::suspend((int) $sub['id']);
            AuditService::log('suspend', 'platform_admin', 'organization', $id);
            Session::flash('success', 'Organization suspended.');
        }
        $this->back('/platform-admin/organizations');
    }

    public function reactivateOrganization(Request $request): void
    {
        $id = (int) $request->param('id');
        $sub = Database::one("SELECT id FROM subscriptions WHERE organization_id = ?", [$id]);
        if ($sub) {
            SubscriptionService::reactivate((int) $sub['id']);
            AuditService::log('reactivate', 'platform_admin', 'organization', $id);
            Session::flash('success', 'Organization reactivated.');
        }
        $this->back('/platform-admin/organizations');
    }

    public function plans(Request $request): void
    {
        $plans = Database::all("SELECT * FROM subscription_plans ORDER BY sort_order");
        $this->view('platform-admin/plans', ['pageTitle' => 'Plans', 'plans' => $plans], 'layouts/platform-admin');
    }

    public function storePlan(Request $request): void
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $request->trimmed('slug') ?: $request->trimmed('name')), '-'));
        $name = $request->trimmed('name');

        if ($slug === '' || $name === '') {
            Session::flash('error', 'Enter a plan name.');
            $this->back('/platform-admin/plans');
        }

        Database::execute(
            "INSERT INTO subscription_plans (slug, name, description, monthly_price, yearly_price, max_branches, max_users, max_products, max_transactions_per_month, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)",
            [
                $slug, $name, $request->trimmed('description') ?: null,
                (float) $request->input('monthly_price', 0), (float) $request->input('yearly_price', 0),
                $this->nullableInt($request, 'max_branches'), $this->nullableInt($request, 'max_users'),
                $this->nullableInt($request, 'max_products'), $this->nullableInt($request, 'max_transactions_per_month'),
                (int) $request->input('sort_order', 0),
            ]
        );
        $id = (int) Database::lastInsertId();
        AuditService::log('create', 'platform_admin', 'subscription_plan', $id, null, ['name' => $name]);
        Session::flash('success', 'Plan added.');
        $this->back('/platform-admin/plans');
    }

    public function updatePlan(Request $request): void
    {
        $id = (int) $request->param('id');
        $name = $request->trimmed('name');
        if ($name === '') {
            Session::flash('error', 'Enter a plan name.');
            $this->back('/platform-admin/plans');
        }

        Database::execute(
            "UPDATE subscription_plans SET name = ?, description = ?, monthly_price = ?, yearly_price = ?,
                max_branches = ?, max_users = ?, max_products = ?, max_transactions_per_month = ?, sort_order = ?, updated_at = NOW()
             WHERE id = ?",
            [
                $name, $request->trimmed('description') ?: null,
                (float) $request->input('monthly_price', 0), (float) $request->input('yearly_price', 0),
                $this->nullableInt($request, 'max_branches'), $this->nullableInt($request, 'max_users'),
                $this->nullableInt($request, 'max_products'), $this->nullableInt($request, 'max_transactions_per_month'),
                (int) $request->input('sort_order', 0), $id,
            ]
        );
        AuditService::log('update', 'platform_admin', 'subscription_plan', $id);
        Session::flash('success', 'Plan updated.');
        $this->back('/platform-admin/plans');
    }

    public function togglePlan(Request $request): void
    {
        $id = (int) $request->param('id');
        Database::execute("UPDATE subscription_plans SET is_active = NOT is_active WHERE id = ?", [$id]);
        AuditService::log('update', 'platform_admin', 'subscription_plan', $id, null, ['toggled_active' => true]);
        Session::flash('success', 'Plan updated.');
        $this->back('/platform-admin/plans');
    }

    public function payments(Request $request): void
    {
        $status = $request->trimmed('status') ?: 'pending';
        $where = in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true) ? "sp.status = ?" : "1=1";
        $params = $where === "sp.status = ?" ? [$status] : [];

        $payments = Database::all(
            "SELECT sp.*, o.name AS organization_name, pl.name AS plan_name, u.name AS submitted_by_name
             FROM subscription_payments sp
             JOIN organizations o ON o.id = sp.organization_id
             JOIN subscription_plans pl ON pl.id = sp.subscription_plan_id
             LEFT JOIN users u ON u.id = sp.submitted_by
             WHERE {$where} ORDER BY sp.created_at DESC",
            $params
        );

        $this->view('platform-admin/payments', [
            'pageTitle' => 'Subscription Payments',
            'payments' => $payments,
            'status' => $status,
        ], 'layouts/platform-admin');
    }

    public function approvePayment(Request $request): void
    {
        $id = (int) $request->param('id');
        $payment = Database::one("SELECT * FROM subscription_payments WHERE id = ? AND status = 'pending'", [$id]);
        if (!$payment) {
            Session::flash('error', 'Payment not found or already reviewed.');
            $this->back('/platform-admin/payments');
        }

        SubscriptionService::approvePaymentAndRenew((int) $payment['subscription_id'], (int) $payment['subscription_plan_id'], $payment['billing_period']);

        Database::execute(
            "UPDATE subscription_payments SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?",
            [Auth::id(), $id]
        );

        AuditService::log('approve', 'platform_admin', 'subscription_payment', $id, null, ['organization_id' => $payment['organization_id'], 'amount' => $payment['amount']]);
        Session::flash('success', 'Payment approved — subscription renewed.');
        $this->back('/platform-admin/payments');
    }

    public function rejectPayment(Request $request): void
    {
        $id = (int) $request->param('id');
        $payment = Database::one("SELECT id FROM subscription_payments WHERE id = ? AND status = 'pending'", [$id]);
        if (!$payment) {
            Session::flash('error', 'Payment not found or already reviewed.');
            $this->back('/platform-admin/payments');
        }

        Database::execute(
            "UPDATE subscription_payments SET status = 'rejected', notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?",
            [$request->trimmed('reason') ?: null, Auth::id(), $id]
        );

        AuditService::log('reject', 'platform_admin', 'subscription_payment', $id);
        Session::flash('success', 'Payment rejected.');
        $this->back('/platform-admin/payments');
    }

    public function settings(Request $request): void
    {
        $this->view('platform-admin/settings', [
            'pageTitle' => 'Platform Settings',
            'trialDays' => (int) PlatformSettingsService::get('trial_days', '14'),
            'platformName' => PlatformSettingsService::get('platform_name', 'Sukli'),
            'themeColor' => PlatformSettingsService::get('theme_color', PlatformSettingsService::DEFAULT_ACCENT),
            'themeFont' => PlatformSettingsService::get('theme_font', PlatformSettingsService::DEFAULT_FONT),
            'fontChoices' => PlatformSettingsService::FONT_CHOICES,
        ], 'layouts/platform-admin');
    }

    public function updateSettings(Request $request): void
    {
        $trialDays = max(1, (int) $request->input('trial_days', 14));
        $platformName = $request->trimmed('platform_name') ?: 'Sukli';

        PlatformSettingsService::set('trial_days', (string) $trialDays);
        PlatformSettingsService::set('platform_name', $platformName);

        AuditService::log('update', 'platform_admin', 'platform_settings', 0);
        Session::flash('success', 'Settings saved.');
        $this->redirect('/platform-admin/settings');
    }

    public function updateAppearance(Request $request): void
    {
        $color = strtolower(trim($request->trimmed('theme_color') ?? ''));
        $font = $request->trimmed('theme_font') ?? '';

        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            Session::flash('error', 'Enter a valid color (e.g. #16a34a).');
            $this->redirect('/platform-admin/settings');
        }
        if (!isset(PlatformSettingsService::FONT_CHOICES[$font])) {
            Session::flash('error', 'Select a valid font.');
            $this->redirect('/platform-admin/settings');
        }

        PlatformSettingsService::set('theme_color', $color);
        PlatformSettingsService::set('theme_font', $font);

        AuditService::log('update', 'platform_admin', 'platform_theme', 0, null, ['theme_color' => $color, 'theme_font' => $font]);
        Session::flash('success', 'Appearance saved.');
        $this->redirect('/platform-admin/settings');
    }

    public function resetAppearance(Request $request): void
    {
        PlatformSettingsService::set('theme_color', PlatformSettingsService::DEFAULT_ACCENT);
        PlatformSettingsService::set('theme_font', PlatformSettingsService::DEFAULT_FONT);

        AuditService::log('update', 'platform_admin', 'platform_theme', 0, null, ['reset' => true]);
        Session::flash('success', 'Appearance reset to default.');
        $this->redirect('/platform-admin/settings');
    }

    private function nullableInt(Request $request, string $key): ?int
    {
        $raw = $request->trimmed($key);
        return $raw === '' || $raw === null ? null : (int) $raw;
    }
}
