<?php
/** @var array $customers */
/** @var string $search */
?>
<div class="flex items-center justify-between mb-16" style="flex-wrap:wrap;gap:10px;">
    <div>
        <h2 style="margin:0;">Customers</h2>
        <p class="text-muted" style="margin:0;">Manage customer records and credit accounts</p>
    </div>
    <button type="button" class="btn btn-primary" data-modal-target="#add-customer"><?= icon('plus', 16) ?> Add Customer</button>
</div>

<div class="card mb-16">
    <form method="get" action="<?= url('/customers') ?>" class="flex gap-12">
        <input class="form-control" type="text" name="q" value="<?= e($search) ?>" placeholder="Search name or contact number">
        <button type="submit" class="btn btn-outline"><?= icon('search', 16) ?></button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>Contact</th><th>Outstanding Balance</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><strong><?= e($c['name']) ?></strong></td>
                    <td class="text-muted"><?= e($c['contact_number'] ?? '—') ?></td>
                    <td><?= (float) $c['outstanding_balance'] > 0 ? '<span class="badge badge-amber">' . money($c['outstanding_balance']) . '</span>' : money(0) ?></td>
                    <td><span class="badge <?= $c['status'] === 'active' ? 'badge-green' : 'badge-gray' ?>"><?= e(ucfirst($c['status'])) ?></span></td>
                    <td>
                        <div class="flex gap-8">
                            <button type="button" class="btn btn-sm btn-outline" data-modal-target="#edit-customer-<?= $c['id'] ?>"><?= icon('edit', 14) ?></button>
                            <?php if ((float) $c['outstanding_balance'] > 0): ?>
                                <a href="<?= url('/utang/' . $c['id']) ?>" class="btn btn-sm btn-outline">Utang</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$customers): ?><tr><td colspan="5" class="text-muted">No customers yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop" id="add-customer">
    <div class="modal">
        <h3>Add Customer</h3>
        <form method="post" action="<?= url('/customers') ?>">
            <?= csrf_field() ?>
            <div class="form-group"><label>Name</label><input class="form-control" name="name" required></div>
            <div class="form-group"><label>Contact Number</label><input class="form-control" name="contact_number"></div>
            <div class="form-group"><label>Address</label><input class="form-control" name="address"></div>
            <div class="form-group"><label>Notes</label><input class="form-control" name="notes"></div>
            <div class="flex gap-8"><button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button><button class="btn btn-primary btn-block">Save</button></div>
        </form>
    </div>
</div>

<?php foreach ($customers as $c): ?>
<div class="modal-backdrop" id="edit-customer-<?= $c['id'] ?>">
    <div class="modal">
        <h3>Edit Customer</h3>
        <form method="post" action="<?= url('/customers/' . $c['id']) ?>">
            <?= csrf_field() ?>
            <div class="form-group"><label>Name</label><input class="form-control" name="name" value="<?= e($c['name']) ?>" required></div>
            <div class="form-group"><label>Contact Number</label><input class="form-control" name="contact_number" value="<?= e($c['contact_number'] ?? '') ?>"></div>
            <div class="form-group"><label>Address</label><input class="form-control" name="address" value="<?= e($c['address'] ?? '') ?>"></div>
            <div class="form-group"><label>Notes</label><input class="form-control" name="notes" value="<?= e($c['notes'] ?? '') ?>"></div>
            <div class="flex gap-8"><button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button><button class="btn btn-primary btn-block">Save</button></div>
        </form>
        <form method="post" action="<?= url('/customers/' . $c['id']) ?>" class="mt-16">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="toggle_status">
            <input type="hidden" name="name" value="<?= e($c['name']) ?>">
            <button class="btn btn-outline btn-block"><?= $c['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?></button>
        </form>
    </div>
</div>
<?php endforeach; ?>
