<?php
/** @var array $branches */
/** @var int|null $limit */
/** @var int $activeCount */
/** @var bool $canAddMore */
?>
<div class="flex items-center justify-between mb-16" style="flex-wrap:wrap;gap:10px;">
    <div>
        <h2 style="margin:0;">Branches</h2>
        <p class="text-muted" style="margin:0;">
            <?= $activeCount ?> active branch<?= $activeCount === 1 ? '' : 'es' ?><?= $limit !== null ? ' of ' . $limit . ' allowed on your plan' : '' ?>
        </p>
    </div>
    <?php if ($canAddMore): ?>
        <button type="button" class="btn btn-primary" data-modal-target="#add-branch"><?= icon('plus', 16) ?> Add Branch</button>
    <?php else: ?>
        <a href="<?= url('/subscription') ?>" class="btn btn-outline">Upgrade to add more branches</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Branch</th><th>Code</th><th>Address</th><th>Phone</th><th>Users</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($branches as $b): ?>
                <tr>
                    <td>
                        <strong><?= e($b['name']) ?></strong>
                        <?php if ($b['is_main_branch']): ?><span class="badge badge-blue" style="margin-left:6px;">Main</span><?php endif; ?>
                    </td>
                    <td class="text-muted"><?= e($b['branch_code'] ?? '—') ?></td>
                    <td class="text-muted"><?= e($b['address'] ?? '—') ?></td>
                    <td class="text-muted"><?= e($b['phone'] ?? '—') ?></td>
                    <td class="text-muted"><?= (int) $b['user_count'] ?></td>
                    <td><span class="badge <?= $b['status'] === 'active' ? 'badge-green' : 'badge-gray' ?>"><?= e(ucfirst($b['status'])) ?></span></td>
                    <td class="flex gap-8">
                        <button type="button" class="btn btn-sm btn-outline" data-modal-target="#edit-branch-<?= $b['id'] ?>">Edit</button>
                        <?php if (!$b['is_main_branch']): ?>
                            <form method="post" action="<?= url('/branches/' . $b['id'] . '/toggle') ?>"
                                  onsubmit="return confirm('<?= $b['status'] === 'active' ? 'Disable' : 'Enable' ?> this branch?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline"><?= $b['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>

                <div class="modal-backdrop" id="edit-branch-<?= $b['id'] ?>">
                    <div class="modal">
                        <h3>Edit Branch</h3>
                        <form method="post" action="<?= url('/branches/' . $b['id']) ?>">
                            <?= csrf_field() ?>
                            <div class="form-group"><label>Branch Name</label><input class="form-control" name="name" value="<?= e($b['name']) ?>" required></div>
                            <div class="form-group"><label>Branch Code</label><input class="form-control" name="branch_code" value="<?= e($b['branch_code'] ?? '') ?>" placeholder="e.g. BR2"></div>
                            <div class="form-group"><label>Address</label><input class="form-control" name="address" value="<?= e($b['address'] ?? '') ?>"></div>
                            <div class="form-group"><label>Phone</label><input class="form-control" name="phone" value="<?= e($b['phone'] ?? '') ?>"></div>
                            <div class="flex gap-8"><button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button><button class="btn btn-primary btn-block">Save</button></div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$branches): ?><tr><td colspan="7" class="text-muted">No branches yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop" id="add-branch">
    <div class="modal">
        <h3>Add Branch</h3>
        <form method="post" action="<?= url('/branches') ?>">
            <?= csrf_field() ?>
            <div class="form-group"><label>Branch Name</label><input class="form-control" name="name" placeholder="e.g. Market Branch" required></div>
            <div class="form-group"><label>Branch Code</label><input class="form-control" name="branch_code" placeholder="e.g. BR2"></div>
            <div class="form-group"><label>Address</label><input class="form-control" name="address"></div>
            <div class="form-group"><label>Phone</label><input class="form-control" name="phone"></div>
            <div class="flex gap-8"><button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button><button class="btn btn-primary btn-block">Add Branch</button></div>
        </form>
    </div>
</div>
