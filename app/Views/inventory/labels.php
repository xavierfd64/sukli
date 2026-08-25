<?php
/** @var array $products */
/** @var array $preselected */
/** @var array $paperSizes */
?>
<div class="flex items-center justify-between mb-16" style="flex-wrap:wrap;gap:10px;">
    <div>
        <h2 style="margin:0;">Print Barcode Labels</h2>
        <p class="text-muted" style="margin:0;">Choose products, how many copies of each, and a paper size.</p>
    </div>
    <a href="<?= url('/inventory') ?>" class="btn btn-outline">Back to Inventory</a>
</div>

<form method="post" action="<?= url('/inventory/labels/print') ?>" target="_blank" id="labels-config-form">
    <?= csrf_field() ?>

    <div class="card mb-16">
        <div class="flex gap-12" style="flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin:0;flex:1;min-width:220px;">
                <label>Search products</label>
                <input type="text" class="form-control" id="labels-search" placeholder="Filter by product name...">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Paper Size</label>
                <select class="form-control" name="paper_size">
                    <?php foreach ($paperSizes as $key => $label): ?>
                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-block"><?= icon('barcode', 16) ?> Generate Labels</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table" id="labels-table">
                <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="labels-select-all"></th>
                        <th>Product</th>
                        <th>Barcode</th>
                        <th style="width:120px;">Quantity</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p): $checked = in_array((int) $p['id'], $preselected, true); ?>
                    <tr data-name="<?= e(strtolower($p['name'])) ?>">
                        <td><input type="checkbox" class="labels-row-check" name="selected[<?= (int) $p['id'] ?>]" value="1" <?= $checked ? 'checked' : '' ?>></td>
                        <td><?= e($p['name']) ?></td>
                        <td class="text-muted"><?= e($p['barcode'] ?: 'No barcode assigned') ?></td>
                        <td><input type="number" class="form-control form-control-sm" name="quantity[<?= (int) $p['id'] ?>]" value="1" min="1" max="500" style="width:90px;"></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$products): ?><tr><td colspan="4" class="text-muted">No active products yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<script>
(function () {
    var search = document.getElementById('labels-search');
    search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        document.querySelectorAll('#labels-table tbody tr[data-name]').forEach(function (row) {
            row.style.display = row.dataset.name.indexOf(q) === -1 ? 'none' : '';
        });
    });

    var selectAll = document.getElementById('labels-select-all');
    selectAll.addEventListener('change', function () {
        document.querySelectorAll('.labels-row-check').forEach(function (cb) {
            var row = cb.closest('tr');
            if (row.style.display !== 'none') cb.checked = selectAll.checked;
        });
    });
})();
</script>
