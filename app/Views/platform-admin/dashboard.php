<?php
/** @var array $counts */
/** @var array $recentOrgs */
?>
<h2 style="margin-top:0;">Platform Overview</h2>

<div class="grid grid-4 mb-16">
    <div class="card"><div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Total Organizations</div><div style="font-size:22px;font-weight:700;"><?= (int) $counts['total_orgs'] ?></div></div>
    <div class="card"><div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Active</div><div style="font-size:22px;font-weight:700;color:var(--green-dark);"><?= (int) $counts['active_orgs'] ?></div></div>
    <div class="card"><div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Trial</div><div style="font-size:22px;font-weight:700;color:var(--blue);"><?= (int) $counts['trial_orgs'] ?></div></div>
    <div class="card"><div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Expired / Suspended</div><div style="font-size:22px;font-weight:700;color:var(--red);"><?= (int) $counts['expired_orgs'] ?></div></div>
</div>

<div class="grid grid-4 mb-16">
    <div class="card"><div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Total Branches</div><div style="font-size:22px;font-weight:700;"><?= (int) $counts['total_branches'] ?></div></div>
    <div class="card"><div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Total Users</div><div style="font-size:22px;font-weight:700;"><?= (int) $counts['total_users'] ?></div></div>
    <div class="card">
        <div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Pending Payments</div>
        <div style="font-size:22px;font-weight:700;<?= $counts['pending_payments'] > 0 ? 'color:var(--red);' : '' ?>"><?= (int) $counts['pending_payments'] ?></div>
        <?php if ($counts['pending_payments'] > 0): ?><a href="<?= url('/platform-admin/payments') ?>" style="font-size:11.5px;">Review now &rarr;</a><?php endif; ?>
    </div>
    <div class="card"><div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Revenue This Month</div><div style="font-size:22px;font-weight:700;color:var(--green-dark);"><?= money($counts['revenue_this_month']) ?></div></div>
</div>

<div class="card">
    <div class="card-title">Recent Registrations</div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Organization</th><th>Plan</th><th>Status</th><th>Registered</th></tr></thead>
            <tbody>
            <?php foreach ($recentOrgs as $o): ?>
                <tr>
                    <td><a href="<?= url('/platform-admin/organizations/' . $o['id']) ?>"><strong><?= e($o['name']) ?></strong></a></td>
                    <td class="text-muted"><?= e($o['plan_name'] ?? '—') ?></td>
                    <td><span class="badge <?= ['trial' => 'badge-blue', 'active' => 'badge-green', 'expired' => 'badge-red', 'suspended' => 'badge-red', 'cancelled' => 'badge-gray'][$o['subscription_status']] ?? 'badge-gray' ?>"><?= e(ucfirst($o['subscription_status'] ?? 'none')) ?></span></td>
                    <td class="text-muted"><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentOrgs): ?><tr><td colspan="4" class="text-muted">No organizations yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
