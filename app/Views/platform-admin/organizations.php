<?php
/** @var array $orgs */
?>
<h2 style="margin-top:0;">Organizations</h2>
<p class="text-muted">Every business registered on this Sukli platform.</p>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Business</th><th>Owner</th><th>Email</th><th>Plan</th><th>Branches</th><th>Users</th><th>Status</th><th>Registered</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($orgs as $o): ?>
                <tr>
                    <td><a href="<?= url('/platform-admin/organizations/' . $o['id']) ?>"><strong><?= e($o['name']) ?></strong></a></td>
                    <td class="text-muted"><?= e($o['owner_name'] ?? '—') ?></td>
                    <td class="text-muted"><?= e($o['owner_email'] ?? '—') ?></td>
                    <td class="text-muted"><?= e($o['plan_name'] ?? '—') ?></td>
                    <td><?= (int) $o['branch_count'] ?></td>
                    <td><?= (int) $o['user_count'] ?></td>
                    <td><span class="badge <?= ['trial' => 'badge-blue', 'active' => 'badge-green', 'expired' => 'badge-red', 'suspended' => 'badge-red', 'cancelled' => 'badge-gray'][$o['subscription_status']] ?? 'badge-gray' ?>"><?= e(ucfirst($o['subscription_status'] ?? 'none')) ?></span></td>
                    <td class="text-muted"><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                    <td>
                        <?php if ($o['subscription_status'] === 'suspended'): ?>
                            <form method="post" action="<?= url('/platform-admin/organizations/' . $o['id'] . '/reactivate') ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline">Reactivate</button>
                            </form>
                        <?php elseif ($o['subscription_status'] === 'active' || $o['subscription_status'] === 'trial'): ?>
                            <form method="post" action="<?= url('/platform-admin/organizations/' . $o['id'] . '/suspend') ?>" onsubmit="return confirm('Suspend this organization? Its users will lose access until reactivated.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline" style="color:var(--red);">Suspend</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orgs): ?><tr><td colspan="9" class="text-muted">No organizations yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
