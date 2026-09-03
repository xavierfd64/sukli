<?php
/** @var array $org */
/** @var array|null $subscription */
/** @var array $branches */
/** @var array $users */
/** @var array $payments */
?>
<a href="<?= url('/platform-admin/organizations') ?>" class="text-muted" style="font-size:12.5px;">&larr; Back to Organizations</a>
<h2 style="margin:6px 0 2px;"><?= e($org['name']) ?></h2>
<p class="text-muted">Registered <?= date('M d, Y', strtotime($org['created_at'])) ?></p>

<div class="card mb-16">
    <div class="card-title">Subscription</div>
    <?php if ($subscription): ?>
        <div class="flex items-center justify-between" style="font-size:15px;font-weight:700;margin-bottom:6px;">
            <span><?= e($subscription['plan_name']) ?></span>
            <span class="badge <?= ['trial' => 'badge-blue', 'active' => 'badge-green', 'expired' => 'badge-red', 'suspended' => 'badge-red', 'cancelled' => 'badge-gray'][$subscription['status']] ?? 'badge-gray' ?>"><?= e(ucfirst($subscription['status'])) ?></span>
        </div>
        <div class="text-muted" style="font-size:12.5px;">
            <?php if ($subscription['status'] === 'trial'): ?>
                Trial ends <?= $subscription['trial_ends_at'] ? date('M d, Y', strtotime($subscription['trial_ends_at'])) : '—' ?>
            <?php else: ?>
                Period ends <?= $subscription['current_period_end'] ? date('M d, Y', strtotime($subscription['current_period_end'])) : '—' ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="text-muted">No subscription record.</p>
    <?php endif; ?>
</div>

<div class="grid grid-2 mb-16">
    <div class="card">
        <div class="card-title">Branches (<?= count($branches) ?>)</div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Code</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($branches as $b): ?>
                    <tr>
                        <td><?= e($b['name']) ?><?= $b['is_main_branch'] ? ' <span class="badge badge-blue">Main</span>' : '' ?></td>
                        <td class="text-muted"><?= e($b['branch_code'] ?? '—') ?></td>
                        <td><span class="badge <?= $b['status'] === 'active' ? 'badge-green' : 'badge-gray' ?>"><?= e(ucfirst($b['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Users (<?= count($users) ?>)</div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Role</th><th>Branch</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= e($u['name']) ?><div class="text-muted" style="font-size:11px;"><?= e($u['username']) ?></div></td>
                        <td><span class="badge badge-blue"><?= e($u['role_name']) ?></span></td>
                        <td class="text-muted"><?= e($u['branch_name'] ?? '—') ?></td>
                        <td><span class="badge <?= $u['status'] === 'active' ? 'badge-green' : 'badge-gray' ?>"><?= e(ucfirst($u['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-title">Payment History</div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Date</th><th>Plan</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td class="text-muted"><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                    <td><?= e($p['plan_name']) ?></td>
                    <td><?= money($p['amount']) ?></td>
                    <td class="text-muted"><?= e(ucfirst(str_replace('_', ' ', $p['payment_method']))) ?></td>
                    <td><span class="badge <?= ['pending' => 'badge-blue', 'approved' => 'badge-green', 'rejected' => 'badge-red', 'cancelled' => 'badge-gray'][$p['status']] ?? 'badge-gray' ?>"><?= e(ucfirst($p['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$payments): ?><tr><td colspan="5" class="text-muted">No payments submitted yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
