<?php
/** @var array $plans */
?>
<h2 style="margin-top:0;">Subscription Plans</h2>
<p class="text-muted">Database-driven — nothing in the app hardcodes these prices or limits. Leave a limit blank for unlimited.</p>

<div class="card mb-16">
    <div class="card-title">Add Plan</div>
    <form method="post" action="<?= url('/platform-admin/plans') ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group"><label>Name</label><input class="form-control" name="name" placeholder="e.g. Pro" required></div>
            <div class="form-group"><label>Slug</label><input class="form-control" name="slug" placeholder="auto from name if blank"></div>
        </div>
        <div class="form-group"><label>Description</label><input class="form-control" name="description"></div>
        <div class="form-row">
            <div class="form-group"><label>Monthly Price</label><input class="form-control" type="number" step="0.01" min="0" name="monthly_price" value="0" required></div>
            <div class="form-group"><label>Yearly Price</label><input class="form-control" type="number" step="0.01" min="0" name="yearly_price" value="0" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Max Branches</label><input class="form-control" type="number" min="0" name="max_branches" placeholder="Unlimited"></div>
            <div class="form-group"><label>Max Users</label><input class="form-control" type="number" min="0" name="max_users" placeholder="Unlimited"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Max Products</label><input class="form-control" type="number" min="0" name="max_products" placeholder="Unlimited"></div>
            <div class="form-group"><label>Max Transactions/Month</label><input class="form-control" type="number" min="0" name="max_transactions_per_month" placeholder="Unlimited"></div>
        </div>
        <button type="submit" class="btn btn-primary">Add Plan</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Plan</th><th>Monthly</th><th>Yearly</th><th>Branches</th><th>Users</th><th>Products</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($plans as $p): $fid = 'plan-edit-' . $p['id']; ?>
                <tr>
                    <td><input class="form-control form-control-sm" name="name" form="<?= $fid ?>" value="<?= e($p['name']) ?>" required style="min-width:100px;"></td>
                    <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="monthly_price" form="<?= $fid ?>" value="<?= e($p['monthly_price']) ?>" style="width:90px;"></td>
                    <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="yearly_price" form="<?= $fid ?>" value="<?= e($p['yearly_price']) ?>" style="width:90px;"></td>
                    <td><input class="form-control form-control-sm" type="number" min="0" name="max_branches" form="<?= $fid ?>" value="<?= e($p['max_branches']) ?>" placeholder="∞" style="width:70px;"></td>
                    <td><input class="form-control form-control-sm" type="number" min="0" name="max_users" form="<?= $fid ?>" value="<?= e($p['max_users']) ?>" placeholder="∞" style="width:70px;"></td>
                    <td><input class="form-control form-control-sm" type="number" min="0" name="max_products" form="<?= $fid ?>" value="<?= e($p['max_products']) ?>" placeholder="∞" style="width:70px;"></td>
                    <td><span class="badge <?= $p['is_active'] ? 'badge-green' : 'badge-gray' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td class="flex gap-8">
                        <button type="submit" form="<?= $fid ?>" class="btn btn-sm btn-outline">Save</button>
                        <form method="post" action="<?= url('/platform-admin/plans/' . $p['id'] . '/toggle') ?>" style="display:contents;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline"><?= $p['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$plans): ?><tr><td colspan="8" class="text-muted">No plans yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php foreach ($plans as $p): ?>
    <form id="plan-edit-<?= (int) $p['id'] ?>" method="post" action="<?= url('/platform-admin/plans/' . $p['id']) ?>" style="display:none;">
        <?= csrf_field() ?>
        <input type="hidden" name="description" value="<?= e($p['description'] ?? '') ?>">
        <input type="hidden" name="max_transactions_per_month" value="<?= e($p['max_transactions_per_month']) ?>">
        <input type="hidden" name="sort_order" value="<?= e($p['sort_order']) ?>">
    </form>
<?php endforeach; ?>
