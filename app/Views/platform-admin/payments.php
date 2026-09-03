<?php
/** @var array $payments */
/** @var string $status */
?>
<h2 style="margin-top:0;">Subscription Payments</h2>

<div class="flex gap-8 mb-16">
    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $key => $label): ?>
        <a href="<?= url('/platform-admin/payments?status=' . $key) ?>" class="btn btn-sm <?= $status === $key ? 'btn-primary' : 'btn-outline' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Organization</th><th>Plan</th><th>Billing</th><th>Amount</th><th>Method</th><th>Reference</th><th>Proof</th><th>Submitted By</th><th>Date</th>
                <?php if ($status === 'pending'): ?><th>Actions</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td><a href="<?= url('/platform-admin/organizations/' . $p['organization_id']) ?>"><strong><?= e($p['organization_name']) ?></strong></a></td>
                    <td class="text-muted"><?= e($p['plan_name']) ?></td>
                    <td class="text-muted"><?= e(ucfirst($p['billing_period'])) ?></td>
                    <td><?= money($p['amount']) ?></td>
                    <td class="text-muted"><?= e(ucfirst(str_replace('_', ' ', $p['payment_method']))) ?></td>
                    <td class="text-muted"><?= e($p['reference_no'] ?? '—') ?></td>
                    <td><?php if (\Sukli\Services\UploadService::exists($p['proof_path'] ?? null)): ?><a href="<?= e(\Sukli\Services\UploadService::url($p['proof_path'])) ?>" target="_blank" rel="noopener"><?= icon('paperclip', 14) ?> View</a><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                    <td class="text-muted"><?= e($p['submitted_by_name'] ?? '—') ?></td>
                    <td class="text-muted"><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                    <?php if ($status === 'pending'): ?>
                        <td class="flex gap-8">
                            <form method="post" action="<?= url('/platform-admin/payments/' . $p['id'] . '/approve') ?>" onsubmit="return confirm('Approve this payment and renew the subscription?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-primary">Approve</button>
                            </form>
                            <form method="post" action="<?= url('/platform-admin/payments/' . $p['id'] . '/reject') ?>" onsubmit="return confirm('Reject this payment?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline" style="color:var(--red);">Reject</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$payments): ?><tr><td colspan="10" class="text-muted">No <?= e($status) ?> payments.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
