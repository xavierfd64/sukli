<?php /** @var array $suppliers */ ?>
<div class="flex items-center justify-between mb-16" style="flex-wrap:wrap;gap:10px;">
    <div>
        <h2 style="margin:0;">Suppliers</h2>
        <p class="text-muted" style="margin:0;">Manage supplier records</p>
    </div>
    <button type="button" class="btn btn-primary" data-modal-target="#add-supplier"><?= icon('plus', 16) ?> Add Supplier</button>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>Contact</th><th>Address</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td><strong><?= e($s['name']) ?></strong></td>
                    <td class="text-muted"><?= e($s['contact_number'] ?? '—') ?></td>
                    <td class="text-muted"><?= e($s['address'] ?? '—') ?></td>
                    <td><span class="badge <?= $s['status'] === 'active' ? 'badge-green' : 'badge-gray' ?>"><?= e(ucfirst($s['status'])) ?></span></td>
                    <td><button type="button" class="btn btn-sm btn-outline" data-modal-target="#edit-supplier-<?= $s['id'] ?>"><?= icon('edit', 14) ?></button></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$suppliers): ?><tr><td colspan="5" class="text-muted">No suppliers yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop" id="add-supplier">
    <div class="modal">
        <h3>Add Supplier</h3>
        <form method="post" action="<?= url('/suppliers') ?>">
            <?= csrf_field() ?>
            <div class="form-group"><label>Name</label><input class="form-control" name="name" required></div>
            <div class="form-group"><label>Contact Number</label><input class="form-control" name="contact_number"></div>
            <div class="form-group"><label>Address</label><input class="form-control" name="address"></div>
            <div class="form-group"><label>Notes</label><input class="form-control" name="notes"></div>
            <div class="flex gap-8"><button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button><button class="btn btn-primary btn-block">Save</button></div>
        </form>
    </div>
</div>

<?php foreach ($suppliers as $s): ?>
<div class="modal-backdrop" id="edit-supplier-<?= $s['id'] ?>">
    <div class="modal">
        <h3>Edit Supplier</h3>
        <form method="post" action="<?= url('/suppliers/' . $s['id']) ?>">
            <?= csrf_field() ?>
            <div class="form-group"><label>Name</label><input class="form-control" name="name" value="<?= e($s['name']) ?>" required></div>
            <div class="form-group"><label>Contact Number</label><input class="form-control" name="contact_number" value="<?= e($s['contact_number'] ?? '') ?>"></div>
            <div class="form-group"><label>Address</label><input class="form-control" name="address" value="<?= e($s['address'] ?? '') ?>"></div>
            <div class="form-group"><label>Notes</label><input class="form-control" name="notes" value="<?= e($s['notes'] ?? '') ?>"></div>
            <div class="flex gap-8"><button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button><button class="btn btn-primary btn-block">Save</button></div>
        </form>
        <form method="post" action="<?= url('/suppliers/' . $s['id']) ?>" class="mt-16">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="toggle_status">
            <input type="hidden" name="name" value="<?= e($s['name']) ?>">
            <button class="btn btn-outline btn-block"><?= $s['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?></button>
        </form>
    </div>
</div>
<?php endforeach; ?>
