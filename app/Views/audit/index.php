<?php
/** @var array $logs */
/** @var array $modules */
/** @var array $users */
/** @var string $module */
/** @var string $userId */
/** @var int $page */
/** @var int $totalPages */
?>
<h2 style="margin-top:0;">Audit Log</h2>
<p class="text-muted">A record of important actions across the system. Audit logs cannot be edited or deleted from the app.</p>

<div class="card mb-16">
    <form method="get" action="<?= url('/audit-log') ?>" class="flex gap-12" style="flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="margin:0;">
            <label>Module</label>
            <select class="form-control" name="module">
                <option value="">All Modules</option>
                <?php foreach ($modules as $m): ?><option value="<?= e($m['module']) ?>" <?= $m['module'] === $module ? 'selected' : '' ?>><?= e(ucfirst($m['module'])) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>User</label>
            <select class="form-control" name="user_id">
                <option value="">All Users</option>
                <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= (string) $u['id'] === (string) $userId ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-outline">Filter</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>IP Address</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td class="text-muted"><?= date('M d, Y h:i:s A', strtotime($l['created_at'])) ?></td>
                    <td><?= e($l['user_name'] ?? 'System') ?></td>
                    <td><span class="badge badge-blue"><?= e($l['action']) ?></span></td>
                    <td><?= e($l['module']) ?></td>
                    <td class="text-muted"><?= e($l['related_type'] ?? '') ?> <?= $l['related_id'] ? '#' . $l['related_id'] : '' ?></td>
                    <td class="text-muted"><?= e($l['ip_address'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?><tr><td colspan="6" class="text-muted">No audit entries match this filter.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="flex gap-8 mt-16">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>" href="<?= url('/audit-log?page=' . $i . ($module ? '&module=' . $module : '') . ($userId ? '&user_id=' . $userId : '')) ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
