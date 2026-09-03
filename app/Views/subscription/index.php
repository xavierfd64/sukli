<?php
/** @var array|null $subscription */
/** @var array $plans */
/** @var array $payments */
/** @var array $usage */
/** @var int $daysRemaining */
/** @var bool $canManage */

$statusBadge = [
    'trial' => 'badge-blue',
    'active' => 'badge-green',
    'expired' => 'badge-red',
    'suspended' => 'badge-red',
    'cancelled' => 'badge-gray',
][$subscription['status'] ?? ''] ?? 'badge-gray';
?>
<h2 style="margin-top:0;">Subscription</h2>
<p class="text-muted">Your plan, usage, and billing history.</p>

<?php if ($subscription && in_array($subscription['status'], ['expired', 'suspended'], true)): ?>
<div class="alert alert-error mb-16">
    <strong><?= $subscription['status'] === 'expired' ? 'Your subscription has expired.' : 'Your subscription is suspended.' ?></strong>
    Access to Sukli is limited until you renew below. Your data is safe and nothing has been deleted.
</div>
<?php elseif ($subscription && $subscription['status'] === 'trial' && $daysRemaining <= 3): ?>
<div class="alert alert-error mb-16">
    <strong>Your free trial ends in <?= $daysRemaining ?> day<?= $daysRemaining === 1 ? '' : 's' ?>.</strong>
    Choose a plan below to keep your access after it ends.
</div>
<?php endif; ?>

<div class="grid grid-2 mb-16">
    <div class="card">
        <div class="card-title">Current Plan</div>
        <?php if ($subscription): ?>
            <div class="flex items-center justify-between" style="font-size:16px;font-weight:700;margin-bottom:6px;">
                <span><?= e($subscription['plan_name']) ?></span>
                <span class="badge <?= $statusBadge ?>"><?= e(ucfirst($subscription['status'])) ?></span>
            </div>
            <?php if ($subscription['status'] === 'trial' && $subscription['trial_ends_at']): ?>
                <div class="text-muted" style="font-size:12.5px;">Trial ends <?= date('M d, Y', strtotime($subscription['trial_ends_at'])) ?> (<?= $daysRemaining ?> day<?= $daysRemaining === 1 ? '' : 's' ?> left)</div>
            <?php elseif ($subscription['current_period_end']): ?>
                <div class="text-muted" style="font-size:12.5px;">
                    <?= $subscription['status'] === 'active' ? 'Renews' : 'Ended' ?> <?= date('M d, Y', strtotime($subscription['current_period_end'])) ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-muted">No subscription found. Contact support.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">Usage</div>
        <?php foreach (['branches' => 'Branches', 'users' => 'Users', 'products' => 'Products', 'transactions' => 'Transactions this month'] as $key => $label): ?>
            <div class="flex items-center justify-between" style="font-size:12.5px;margin-bottom:6px;">
                <span class="text-muted"><?= $label ?></span>
                <span style="<?= $usage[$key]['ok'] ? '' : 'color:var(--red);font-weight:700;' ?>">
                    <?= $usage[$key]['used'] ?> / <?= $usage[$key]['limit'] === null ? '∞' : $usage[$key]['limit'] ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="card mb-16">
    <div class="card-title">Renew or Upgrade</div>
    <p class="text-muted" style="font-size:12.5px;">Submit payment via GCash, bank transfer, or another method — a Platform Admin will review and approve it.</p>

    <div class="grid grid-4 mb-16">
        <?php foreach ($plans as $p): ?>
            <div class="card" style="border:1px solid var(--border);<?= ($subscription && $subscription['subscription_plan_id'] == $p['id']) ? 'border-color:var(--blue);box-shadow:0 0 0 2px var(--blue) inset;' : '' ?>">
                <div style="font-weight:700;"><?= e($p['name']) ?></div>
                <div class="text-muted" style="font-size:11.5px;margin-bottom:8px;"><?= e($p['description'] ?? '') ?></div>
                <div style="font-size:18px;font-weight:700;"><?= money($p['monthly_price']) ?><span class="text-muted" style="font-size:11px;font-weight:400;">/mo</span></div>
                <div class="text-muted" style="font-size:11px;"><?= money($p['yearly_price']) ?>/year</div>
                <div class="text-muted" style="font-size:11px;margin-top:8px;">
                    <?= $p['max_branches'] === null ? 'Unlimited' : $p['max_branches'] ?> branch<?= $p['max_branches'] == 1 ? '' : 'es' ?><br>
                    <?= $p['max_users'] === null ? 'Unlimited' : $p['max_users'] ?> users
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="post" action="<?= url('/subscription/payments') ?>" enctype="multipart/form-data" class="flex gap-12" style="flex-wrap:wrap;align-items:flex-end;">
        <?= csrf_field() ?>
        <div class="form-group" style="margin:0;">
            <label>Plan</label>
            <select class="form-control" name="subscription_plan_id" required>
                <?php foreach ($plans as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ($subscription && $subscription['subscription_plan_id'] == $p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Billing Period</label>
            <select class="form-control" name="billing_period">
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly (2 months free)</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Payment Method</label>
            <select class="form-control" name="payment_method">
                <option value="gcash">GCash</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;"><label>Reference No.</label><input class="form-control" name="reference_no" placeholder="Transaction reference"></div>
        <div class="form-group" style="margin:0;"><label>Proof of Payment</label><input class="form-control" type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf"></div>
        <button type="submit" class="btn btn-primary">Submit Payment</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title">Payment History</div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Date</th><th>Plan</th><th>Period</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $pmt): ?>
                <tr>
                    <td class="text-muted"><?= date('M d, Y', strtotime($pmt['created_at'])) ?></td>
                    <td><?= e($pmt['plan_name']) ?></td>
                    <td class="text-muted"><?= e(ucfirst($pmt['billing_period'])) ?></td>
                    <td><?= money($pmt['amount']) ?></td>
                    <td class="text-muted"><?= e(ucfirst(str_replace('_', ' ', $pmt['payment_method']))) ?></td>
                    <td><span class="badge <?= ['pending' => 'badge-blue', 'approved' => 'badge-green', 'rejected' => 'badge-red', 'cancelled' => 'badge-gray'][$pmt['status']] ?? 'badge-gray' ?>"><?= e(ucfirst($pmt['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$payments): ?><tr><td colspan="6" class="text-muted">No payments submitted yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
