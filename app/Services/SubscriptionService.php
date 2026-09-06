<?php

declare(strict_types=1);

namespace Sukli\Services;

use Sukli\Core\Database;

/**
 * The subscription lifecycle for an organization (trial -> active ->
 * expired/suspended/cancelled) and the plan-limit checks enforced against
 * it. Sukli runs on shared hosting with no cron/queue worker (see
 * AGENTS/README), so there is no background job flipping a trial to
 * "expired" the moment it lapses — usable()/status() instead compute
 * liveness from trial_ends_at/current_period_end vs NOW() on every call,
 * and self-heal the stored `status` column the first time they notice it's
 * gone stale. That live check is the actual enforcement mechanism; the
 * stored status is just a cache of the last time someone looked.
 */
class SubscriptionService
{
    /** @return array|null The organization's subscription row (with plan fields joined in), or null if it somehow has none. */
    public static function forOrganization(int $organizationId): ?array
    {
        $row = Database::one(
            "SELECT s.*, p.slug AS plan_slug, p.name AS plan_name, p.monthly_price, p.yearly_price,
                    p.max_branches, p.max_users, p.max_products, p.max_transactions_per_month, p.features
             FROM subscriptions s
             JOIN subscription_plans p ON p.id = s.subscription_plan_id
             WHERE s.organization_id = ?",
            [$organizationId]
        );

        if (!$row) {
            return null;
        }

        self::reconcileStatus($row);

        return $row;
    }

    /** True if the organization currently has usable access (trial not yet ended, or an active period not yet ended). */
    public static function isUsable(int $organizationId): bool
    {
        $sub = self::forOrganization($organizationId);
        if (!$sub) {
            return false;
        }
        return in_array($sub['status'], ['trial', 'active'], true);
    }

    /** Days left in the current trial or billing period; 0 if already ended or not applicable. */
    public static function daysRemaining(array $subscription): int
    {
        $end = $subscription['status'] === 'trial' ? $subscription['trial_ends_at'] : $subscription['current_period_end'];
        if (!$end) {
            return 0;
        }
        $diff = (strtotime($end) - time()) / 86400;
        return max(0, (int) ceil($diff));
    }

    /** @return int|null The plan's configured limit column for this resource. Null limit column name throws — an unknown $resource is a programming error, not user input. */
    public static function limitFor(?array $subscription, string $resource): ?int
    {
        if (!$subscription) {
            return null;
        }
        $limitColumn = self::limitColumn($resource);
        return $subscription[$limitColumn];
    }

    /** Current usage count for one of: branches, users, products, transactions (transactions = completed sales so far this calendar month). */
    public static function usage(int $organizationId, string $resource): int
    {
        return match ($resource) {
            'branches' => (int) (Database::one("SELECT COUNT(*) AS c FROM stores WHERE organization_id = ? AND status = 'active'", [$organizationId])['c'] ?? 0),
            'users' => (int) (Database::one("SELECT COUNT(*) AS c FROM users WHERE organization_id = ? AND status = 'active'", [$organizationId])['c'] ?? 0),
            'products' => (int) (Database::one(
                "SELECT COUNT(*) AS c FROM products p JOIN stores st ON st.id = p.store_id WHERE st.organization_id = ? AND p.status = 'active'",
                [$organizationId]
            )['c'] ?? 0),
            'transactions' => (int) (Database::one(
                "SELECT COUNT(*) AS c FROM sales s JOIN stores st ON st.id = s.store_id
                 WHERE st.organization_id = ? AND s.status = 'completed' AND s.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')",
                [$organizationId]
            )['c'] ?? 0),
            default => throw new \InvalidArgumentException("Unknown limit resource: {$resource}"),
        };
    }

    /**
     * Checks a plan limit against the organization's current usage.
     * $resource is one of: branches, users, products, transactions.
     * A NULL limit on the plan means unlimited (always passes).
     */
    public static function withinLimit(int $organizationId, string $resource): bool
    {
        $limit = self::limitFor(self::forOrganization($organizationId), $resource);
        return $limit === null || self::usage($organizationId, $resource) < $limit;
    }

    private static function limitColumn(string $resource): string
    {
        return match ($resource) {
            'branches' => 'max_branches',
            'users' => 'max_users',
            'products' => 'max_products',
            'transactions' => 'max_transactions_per_month',
            default => throw new \InvalidArgumentException("Unknown limit resource: {$resource}"),
        };
    }

    public static function activate(int $subscriptionId, int $planId, string $billingPeriod, string $currentPeriodEnd): void
    {
        Database::execute(
            "UPDATE subscriptions SET subscription_plan_id = ?, billing_period = ?, status = 'active',
                current_period_start = COALESCE(current_period_start, NOW()), current_period_end = ?, cancelled_at = NULL
             WHERE id = ?",
            [$planId, $billingPeriod, $currentPeriodEnd, $subscriptionId]
        );
    }

    /** Extends the current period by $days from whichever is later: today, or the existing period end (so renewing early doesn't lose remaining days). */
    public static function extend(int $subscriptionId, int $days): void
    {
        $sub = Database::one("SELECT current_period_end FROM subscriptions WHERE id = ?", [$subscriptionId]);
        $base = ($sub && $sub['current_period_end'] && strtotime($sub['current_period_end']) > time())
            ? $sub['current_period_end']
            : date('Y-m-d H:i:s');

        Database::execute(
            "UPDATE subscriptions SET status = 'active', current_period_end = DATE_ADD(?, INTERVAL ? DAY), cancelled_at = NULL WHERE id = ?",
            [$base, $days, $subscriptionId]
        );
    }

    public static function suspend(int $subscriptionId): void
    {
        Database::execute("UPDATE subscriptions SET status = 'suspended' WHERE id = ?", [$subscriptionId]);
    }

    public static function cancel(int $subscriptionId): void
    {
        Database::execute("UPDATE subscriptions SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?", [$subscriptionId]);
    }

    public static function changePlan(int $subscriptionId, int $planId): void
    {
        Database::execute("UPDATE subscriptions SET subscription_plan_id = ? WHERE id = ?", [$planId, $subscriptionId]);
    }

    /**
     * Approving a subscription_payments row moves the subscription to that
     * payment's plan and extends it by one full billing period from it — the
     * only path that turns a 'pending' payment into actual access. Also
     * persists $billingPeriod onto the subscription row itself: extend()
     * flips status to 'active' but never touched billing_period, so a trial
     * org's first approval left status='active' sitting next to a
     * billing_period column still reading 'trial' forever — a real
     * underlying-state bug, not just a display issue (nothing currently
     * renders billing_period as the status badge, but the two columns
     * disagreeing is exactly the kind of stale state this method must not
     * produce).
     */
    public static function approvePaymentAndRenew(int $subscriptionId, int $planId, string $billingPeriod): void
    {
        self::changePlan($subscriptionId, $planId);
        self::extend($subscriptionId, $billingPeriod === 'yearly' ? 365 : 30);
        Database::execute("UPDATE subscriptions SET billing_period = ? WHERE id = ?", [$billingPeriod, $subscriptionId]);
    }

    /** Platform Admin manually restoring access (e.g. after resolving a support issue) without waiting for a new payment. Does not touch current_period_end — if it's already in the past, the next reconcileStatus() call will flip this straight back to expired, which is the correct behavior for "give them a look, not a free period." */
    public static function reactivate(int $subscriptionId): void
    {
        Database::execute("UPDATE subscriptions SET status = 'active' WHERE id = ?", [$subscriptionId]);
    }

    /**
     * Bulk version of the same self-healing reconcileStatus() does per-row,
     * for a Platform Admin dashboard that needs accurate counts across
     * every organization at once rather than one lazy fix-up per visit.
     */
    public static function reconcileAll(): void
    {
        Database::execute(
            "UPDATE subscriptions SET status = 'expired'
             WHERE (status = 'trial' AND trial_ends_at IS NOT NULL AND trial_ends_at < NOW())
                OR (status = 'active' AND current_period_end IS NOT NULL AND current_period_end < NOW())"
        );
    }

    /** Self-heals a stale status: a trial/active row whose end date has already passed becomes 'expired' the moment anything looks at it. */
    private static function reconcileStatus(array &$row): void
    {
        $now = time();
        $shouldExpire = false;

        if ($row['status'] === 'trial' && $row['trial_ends_at'] && strtotime($row['trial_ends_at']) < $now) {
            $shouldExpire = true;
        } elseif ($row['status'] === 'active' && $row['current_period_end'] && strtotime($row['current_period_end']) < $now) {
            $shouldExpire = true;
        }

        if ($shouldExpire) {
            Database::execute("UPDATE subscriptions SET status = 'expired' WHERE id = ?", [$row['id']]);
            $row['status'] = 'expired';
        }
    }
}
