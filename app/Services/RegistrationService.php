<?php

declare(strict_types=1);

namespace Sukli\Services;

use Sukli\Core\Database;

/**
 * Self-service sign-up: one new organization + its Main Branch + a trial
 * subscription + an Owner account, all in a single transaction. This is
 * the ongoing "new tenant" path — the /install wizard only ever bootstraps
 * the platform once; every business after that arrives through here.
 */
class RegistrationService
{
    /**
     * @return array{organization_id:int, store_id:int, user_id:int}
     * @throws \RuntimeException On a duplicate username or any other validation failure — message is safe to show the registrant directly.
     */
    public static function register(string $ownerName, string $businessName, string $username, ?string $email, string $password): array
    {
        if (Database::one("SELECT id FROM users WHERE username = ?", [$username])) {
            throw new \RuntimeException('That username is already taken.');
        }

        $ownerRole = Database::one("SELECT id FROM roles WHERE role_key = 'owner' AND store_id IS NULL");
        if (!$ownerRole) {
            throw new \RuntimeException('Registration is not available right now. Please contact support.');
        }

        $trialPlan = Database::one("SELECT id FROM subscription_plans WHERE slug = 'trial' AND is_active = 1");
        if (!$trialPlan) {
            throw new \RuntimeException('Registration is not available right now. Please contact support.');
        }

        $trialDays = (int) (Database::one("SELECT setting_value FROM platform_settings WHERE setting_key = 'trial_days'")['setting_value'] ?? 14);

        Database::beginTransaction();
        try {
            Database::execute(
                "INSERT INTO organizations (name, slug, created_at) VALUES (?, ?, NOW())",
                [$businessName, self::uniqueSlug($businessName)]
            );
            $organizationId = (int) Database::lastInsertId();

            Database::execute(
                "INSERT INTO stores (organization_id, name, branch_code, is_main_branch, status, created_at)
                 VALUES (?, ?, 'MAIN', 1, 'active', NOW())",
                [$organizationId, $businessName]
            );
            $storeId = (int) Database::lastInsertId();

            Database::execute(
                "INSERT INTO users (organization_id, store_id, role_id, name, username, email, password_hash, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())",
                [$organizationId, $storeId, $ownerRole['id'], $ownerName, $username, $email, password_hash($password, PASSWORD_DEFAULT)]
            );
            $userId = (int) Database::lastInsertId();

            Database::execute(
                "INSERT INTO user_store_access (user_id, store_id) VALUES (?, ?)",
                [$userId, $storeId]
            );

            Database::execute(
                "INSERT INTO subscriptions (organization_id, subscription_plan_id, billing_period, status, trial_ends_at)
                 VALUES (?, ?, 'trial', 'trial', DATE_ADD(NOW(), INTERVAL ? DAY))",
                [$organizationId, $trialPlan['id'], $trialDays]
            );

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        return ['organization_id' => $organizationId, 'store_id' => $storeId, 'user_id' => $userId];
    }

    private static function uniqueSlug(string $name): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'business';
        $slug = $base;
        $suffix = 2;
        while (Database::one("SELECT id FROM organizations WHERE slug = ?", [$slug])) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }
        return $slug;
    }
}
