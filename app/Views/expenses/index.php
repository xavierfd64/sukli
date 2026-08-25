<?php
/** @var array $records */
/** @var float $total */
/** @var string $from */
/** @var string $to */
/** @var array $categories */
$role = $currentUser['role_key'] ?? null;
$canManage = in_array($role, ['owner', 'manager'], true);
?>
<div class="flex items-center justify-between mb-16" style="flex-wrap:wrap;gap:10px;">
    <div>
        <h2 style="margin:0;">Expenses</h2>
        <p class="text-muted" style="margin:0;">Track store expenses</p>
    </div>
    <button type="button" class="btn btn-primary" data-modal-target="#add-expense"><?= icon('plus', 16) ?> Add Expense</button>
</div>

<div class="card mb-16">
    <form method="get" action="<?= url('/expenses') ?>" class="flex gap-12" style="flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="margin:0;"><label>From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="form-group" style="margin:0;"><label>To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <button type="submit" class="btn btn-outline">Filter</button>
        <div style="margin-left:auto;text-align:right;">
            <div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Total for period</div>
            <div style="font-size:20px;font-weight:700;color:var(--orange);"><?= money($total) ?></div>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Date</th><th>Category</th><th>Amount</th><th>Description</th><th>Receipt</th><th>Recorded By</th><?php if ($canManage): ?><th>Actions</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($records as $r): ?>
                <tr>
                    <td><?= date('M d, Y', strtotime($r['expense_date'])) ?></td>
                    <td><span class="badge badge-amber"><?= e($r['category']) ?></span></td>
                    <td><?= money($r['amount']) ?></td>
                    <td class="text-muted"><?= e($r['description'] ?? '—') ?></td>
                    <td><?php if (!empty($r['receipt_attachment_path'])): ?><a href="<?= e(\Sukli\Services\UploadService::url($r['receipt_attachment_path'])) ?>" target="_blank" class="text-muted"><?= icon('paperclip', 14) ?> View</a><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                    <td class="text-muted"><?= e($r['created_by_name'] ?? '—') ?></td>
                    <?php if ($canManage): ?>
                    <td>
                        <div class="flex gap-8">
                            <button type="button" class="btn btn-sm btn-outline" data-modal-target="#edit-expense-<?= $r['id'] ?>"><?= icon('edit', 14) ?></button>
                            <form method="post" action="<?= url('/expenses/' . $r['id'] . '/delete') ?>" data-confirm="Delete this expense record?">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-danger"><?= icon('trash', 14) ?></button>
                            </form>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$records): ?><tr><td colspan="7" class="text-muted">No expense records for this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop" id="add-expense">
    <div class="modal">
        <h3>Add Expense</h3>
        <form method="post" action="<?= url('/expenses') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group"><label>Date</label><input class="form-control" type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group"><label>Amount</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" required></div>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select class="form-control" name="category">
                    <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Description</label><input class="form-control" name="description" placeholder="Optional notes"></div>
            <div class="form-group"><label>Receipt Attachment</label><input class="form-control" type="file" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf"><div class="form-hint">JPG, PNG, WEBP or PDF — max 5MB.</div></div>
            <div class="flex gap-8"><button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button><button class="btn btn-primary btn-block">Save</button></div>
        </form>
    </div>
</div>

<?php if ($canManage): foreach ($records as $r): ?>
<div class="modal-backdrop" id="edit-expense-<?= $r['id'] ?>">
    <div class="modal">
        <h3>Edit Expense</h3>
        <form method="post" action="<?= url('/expenses/' . $r['id']) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group"><label>Date</label><input class="form-control" type="date" name="expense_date" value="<?= e($r['expense_date']) ?>" required></div>
                <div class="form-group"><label>Amount</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" value="<?= e((string) $r['amount']) ?>" required></div>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select class="form-control" name="category">
                    <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>" <?= $c === $r['category'] ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Description</label><input class="form-control" name="description" value="<?= e($r['description'] ?? '') ?>"></div>
            <div class="form-group">
                <label>Receipt Attachment</label>
                <?php if (!empty($r['receipt_attachment_path'])): ?>
                    <div class="form-hint mb-8"><a href="<?= e(\Sukli\Services\UploadService::url($r['receipt_attachment_path'])) ?>" target="_blank">View current attachment</a> — choose a file below to replace it.</div>
                <?php endif; ?>
                <input class="form-control" type="file" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf">
            </div>
            <div class="flex gap-8"><button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button><button class="btn btn-primary btn-block">Save</button></div>
        </form>
    </div>
</div>
<?php endforeach; endif; ?>
