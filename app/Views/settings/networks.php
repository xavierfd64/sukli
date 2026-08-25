<?php
/** @var array $networks */
?>
<a href="<?= url('/settings') ?>" class="text-muted" style="font-size:12.5px;">&larr; Back to Settings</a>
<h2 style="margin:6px 0 2px;">E-Load Networks</h2>
<p class="text-muted">Manage the list of networks available on the E-Load form.</p>

<div class="card mb-16">
    <form method="post" action="<?= url('/settings/networks') ?>" class="flex gap-8">
        <?= csrf_field() ?>
        <input class="form-control" name="name" placeholder="New network name (e.g. Globe)" required>
        <button type="submit" class="btn btn-outline">Add</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Network</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($networks as $n): ?>
                <tr>
                    <td><strong><?= e($n['name']) ?></strong></td>
                    <td><span class="badge <?= $n['is_enabled'] ? 'badge-green' : 'badge-gray' ?>"><?= $n['is_enabled'] ? 'Enabled' : 'Disabled' ?></span></td>
                    <td>
                        <form method="post" action="<?= url('/settings/networks/' . $n['id'] . '/toggle') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline"><?= $n['is_enabled'] ? 'Disable' : 'Enable' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$networks): ?><tr><td colspan="3" class="text-muted">No networks yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
