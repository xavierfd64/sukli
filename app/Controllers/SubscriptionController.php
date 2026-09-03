<?php

declare(strict_types=1);

namespace Sukli\Controllers;

use Sukli\Core\Auth;
use Sukli\Core\Controller;
use Sukli\Core\Database;
use Sukli\Core\Request;
use Sukli\Core\Session;
use Sukli\Services\AuditService;
use Sukli\Services\SubscriptionService;
use Sukli\Services\UploadService;

/**
 * Self-service billing: view the current subscription (trial/active/
 * expired/...), plan catalog, usage vs. limits, payment history, and
 * submit a new payment (GCash/bank transfer/other) for Platform Admin to
 * approve or reject. Never auto-approves — see SubscriptionController's
 * Platform Admin counterpart for the approval step.
 *
 * Reachable via $authOnly (not $auth) precisely so an expired organization
 * can still get here to pay — see AuthMiddleware.
 */
class SubscriptionController extends Controller
{
    public function index(Request $request): void
    {
        $organizationId = (int) Auth::organizationId();

        $subscription = SubscriptionService::forOrganization($organizationId);
        $plans = Database::all("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order");
        $payments = Database::all(
            "SELECT sp.*, pl.name AS plan_name FROM subscription_payments sp
             JOIN subscription_plans pl ON pl.id = sp.subscription_plan_id
             WHERE sp.organization_id = ? ORDER BY sp.created_at DESC LIMIT 20",
            [$organizationId]
        );

        $usage = [
            'branches' => $this->usageRow($organizationId, 'branches', $subscription),
            'users' => $this->usageRow($organizationId, 'users', $subscription),
            'products' => $this->usageRow($organizationId, 'products', $subscription),
            'transactions' => $this->usageRow($organizationId, 'transactions', $subscription),
        ];

        $this->view('subscription/index', [
            'pageTitle' => 'Subscription',
            'subscription' => $subscription,
            'plans' => $plans,
            'payments' => $payments,
            'usage' => $usage,
            'daysRemaining' => $subscription ? SubscriptionService::daysRemaining($subscription) : 0,
            'canManage' => Auth::hasRole(['owner']),
        ]);
    }

    public function storePayment(Request $request): void
    {
        $organizationId = (int) Auth::organizationId();

        if (!Auth::hasRole(['owner'])) {
            Session::flash('error', 'Only the Organization Owner can submit a subscription payment.');
            $this->redirect('/subscription');
        }

        $subscription = SubscriptionService::forOrganization($organizationId);
        if (!$subscription) {
            Session::flash('error', 'No subscription found for this organization.');
            $this->redirect('/subscription');
        }

        $planId = (int) $request->input('subscription_plan_id', 0);
        $billingPeriod = $request->trimmed('billing_period') === 'yearly' ? 'yearly' : 'monthly';
        $plan = Database::one("SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1", [$planId]);
        if (!$plan) {
            Session::flash('error', 'Select a valid plan.');
            $this->redirect('/subscription');
        }

        $paymentMethod = $request->trimmed('payment_method') ?: 'other';
        $referenceNo = $request->trimmed('reference_no') ?: null;
        $amount = $billingPeriod === 'yearly' ? (float) $plan['yearly_price'] : (float) $plan['monthly_price'];

        try {
            $proofPath = UploadService::store($request->file('proof'), 'subscription-proofs/' . $organizationId);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/subscription');
        }

        Database::execute(
            "INSERT INTO subscription_payments
                (organization_id, subscription_id, subscription_plan_id, billing_period, amount, payment_method, reference_no, proof_path, status, submitted_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())",
            [$organizationId, $subscription['id'], $planId, $billingPeriod, $amount, $paymentMethod, $referenceNo, $proofPath, Auth::id()]
        );
        $paymentId = (int) Database::lastInsertId();

        AuditService::log('subscription_payment_submitted', 'subscription', 'subscription_payment', $paymentId, null, [
            'plan' => $plan['name'],
            'billing_period' => $billingPeriod,
            'amount' => $amount,
        ]);

        Session::flash('success', 'Payment submitted. A Platform Admin will review it shortly.');
        $this->redirect('/subscription');
    }

    private function usageRow(int $organizationId, string $resource, ?array $subscription): array
    {
        $limit = SubscriptionService::limitFor($subscription, $resource);
        $used = SubscriptionService::usage($organizationId, $resource);
        return ['used' => $used, 'limit' => $limit, 'ok' => $limit === null || $used < $limit];
    }
}
