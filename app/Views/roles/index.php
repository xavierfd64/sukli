<?php
/** @var array $roles */
?>
<div class="flex items-center justify-between mb-16" style="flex-wrap:wrap;gap:10px;">
    <div>
        <h2 style="margin:0;">Roles &amp; Permissions</h2>
        <p class="text-muted" style="margin:0;">Control exactly what each role can see and do. Do not give every user full administrator access.</p>
    </div>
    <a href="<?= url('/roles/create') ?>" class="btn btn-primary"><?= icon('plus', 16) ?> Add Role</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Role</th><th>Type</th><th>Users</th><th>Description</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($roles as $r): ?>
                <tr>
                    <td><strong><?= e($r['name']) ?></strong></td>
                    <td><span class="badge <?= $r['is_system'] ? 'badge-blue' : 'badge-purple' ?>"><?= $r['is_system'] ? 'System' : 'Custom' ?></span></td>
                    <td class="text-muted"><?= (int) $r['user_count'] ?></td>
                    <td class="text-muted"><?= e($r['description'] ?? '—') ?></td>
                    <td>
                        <div class="flex gap-8">
                            <a href="<?= url('/roles/' . $r['id'] . '/edit') ?>" class="btn btn-sm btn-outline"><?= icon('edit', 14) ?> Permissions</a>
                            <?php if (!$r['is_system']): ?>
                            <form method="post" action="<?= url('/roles/' . $r['id'] . '/delete') ?>" onsubmit="return confirm('Delete this role?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline" style="color:var(--red);" <?= $r['user_count'] > 0 ? 'disabled title="Reassign its users first"' : '' ?>><?= icon('trash', 14) ?></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
