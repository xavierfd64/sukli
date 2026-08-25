<?php
/** @var array $products */
/** @var array $networks */
?>
<a href="<?= url('/settings') ?>" class="text-muted" style="font-size:12.5px;">&larr; Back to Settings</a>
<h2 style="margin:6px 0 2px;">E-Load Products</h2>
<p class="text-muted">Manage the load products sold per network. Earnings = Selling Price &minus; Cost.</p>

<div class="card mb-16">
    <form method="post" action="<?= url('/settings/eload-products') ?>" class="eload-product-form">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group">
                <label>Network</label>
                <select class="form-control" name="network" required>
                    <option value="">Select network...</option>
                    <?php foreach ($networks as $n): ?>
                        <option value="<?= e($n['name']) ?>"><?= e($n['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Product Name</label><input class="form-control" name="name" placeholder="e.g. GoSAKTO 50" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Cost</label><input class="form-control eload-cost" type="number" step="0.01" min="0" name="cost" value="0" required></div>
            <div class="form-group"><label>Additional Charge</label><input class="form-control eload-charge" type="number" step="0.01" min="0" name="additional_charge" value="0"></div>
            <div class="form-group">
                <label>Selling Price</label>
                <input class="form-control eload-price" type="number" step="0.01" min="0" name="selling_price" value="0" required>
                <div class="form-hint eload-suggest"></div>
            </div>
        </div>
        <button type="submit" class="btn btn-outline">Add Product</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Network</th><th>Product</th><th>Cost</th><th>Additional Charge</th><th>Selling Price</th><th>Earnings</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($products as $p): $earnings = (float) $p['selling_price'] - (float) $p['cost']; $fid = 'eload-edit-' . $p['id']; ?>
                <tr>
                        <td>
                            <select class="form-control form-control-sm" name="network" form="<?= $fid ?>" required>
                                <?php foreach ($networks as $n): ?>
                                    <option value="<?= e($n['name']) ?>" <?= $n['name'] === $p['network'] ? 'selected' : '' ?>><?= e($n['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input class="form-control form-control-sm" name="name" form="<?= $fid ?>" value="<?= e($p['name']) ?>" required style="min-width:140px;"></td>
                        <td><input class="form-control form-control-sm eload-cost" type="number" step="0.01" min="0" name="cost" form="<?= $fid ?>" value="<?= e($p['cost']) ?>" style="width:90px;" required></td>
                        <td><input class="form-control form-control-sm eload-charge" type="number" step="0.01" min="0" name="additional_charge" form="<?= $fid ?>" value="<?= e($p['additional_charge']) ?>" style="width:90px;"></td>
                        <td><input class="form-control form-control-sm eload-price" type="number" step="0.01" min="0" name="selling_price" form="<?= $fid ?>" value="<?= e($p['selling_price']) ?>" style="width:90px;" required></td>
                        <td style="font-weight:600;color:var(--green-dark);"><?= money($earnings) ?></td>
                        <td><span class="badge <?= $p['is_active'] ? 'badge-green' : 'badge-gray' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td class="flex gap-8">
                            <button type="submit" form="<?= $fid ?>" class="btn btn-sm btn-outline">Save</button>
                            <form method="post" action="<?= url('/settings/eload-products/' . $p['id'] . '/toggle') ?>" style="display:contents;">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline"><?= $p['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                        </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$products): ?><tr><td colspan="8" class="text-muted">No E-Load products yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php foreach ($products as $p): ?>
    <form id="eload-edit-<?= (int) $p['id'] ?>" method="post" action="<?= url('/settings/eload-products/' . $p['id']) ?>" style="display:none;"><?= csrf_field() ?></form>
<?php endforeach; ?>

<script>
(function () {
    // Additional Charge is only a hint for a *suggested* selling price —
    // it fills the field once as a convenience but never overwrites a
    // value the admin has already typed or edited.
    function wireSuggestion(scope) {
        var cost = scope.querySelector('.eload-cost');
        var charge = scope.querySelector('.eload-charge');
        var price = scope.querySelector('.eload-price');
        var hint = scope.querySelector('.eload-suggest');
        var priceTouched = parseFloat(price.value) > 0;
        price.addEventListener('input', function () { priceTouched = true; });
        function suggest() {
            var c = parseFloat(cost.value) || 0;
            var a = parseFloat(charge.value) || 0;
            var suggested = c + a;
            if (hint) hint.textContent = suggested > 0 ? ('Suggested: ' + suggested.toFixed(2)) : '';
            if (!priceTouched) { price.value = suggested.toFixed(2); }
        }
        cost.addEventListener('input', suggest);
        charge.addEventListener('input', suggest);
    }
    wireSuggestion(document.querySelector('.eload-product-form'));
    document.querySelectorAll('table tbody tr').forEach(function (row) {
        if (row.querySelector('.eload-cost')) wireSuggestion(row);
    });
})();
</script>
