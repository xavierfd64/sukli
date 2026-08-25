<?php
/** @var array|null $product */
/** @var array $categories */
/** @var array $suppliers */
$isEdit = $product !== null;
?>
<div class="card" style="max-width:720px;">
    <h2 style="margin-top:0;"><?= $isEdit ? 'Edit Product' : 'Add Product' ?></h2>
    <form method="post" action="<?= $isEdit ? url('/inventory/' . $product['id']) : url('/inventory') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Product Image</label>
            <div class="flex items-center gap-16">
                <?php if (\Sukli\Services\UploadService::exists($product['image_path'] ?? null)): ?>
                    <img src="<?= e(\Sukli\Services\UploadService::url($product['image_path'])) ?>" alt="" class="product-thumb product-thumb-lg">
                <?php else: ?>
                    <div class="product-thumb product-thumb-lg product-thumb-placeholder">NO IMAGE</div>
                <?php endif; ?>
                <div style="flex:1;">
                    <input class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp">
                    <div class="form-hint">JPG, PNG or WEBP — max 5MB.</div>
                </div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Product Name</label>
                <input class="form-control" type="text" name="name" value="<?= e($product['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Barcode</label>
                <input class="form-control" type="text" name="barcode" value="<?= e($product['barcode'] ?? '') ?>" placeholder="Leave blank to auto-generate">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" class="form-control">
                    <option value="">Uncategorized</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (string) $c['id'] === (string) ($product['category_id'] ?? '') ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Unit</label>
                <input class="form-control" type="text" name="unit" value="<?= e($product['unit'] ?? 'pc') ?>" placeholder="pc, bottle, pack, sachet...">
            </div>
        </div>
        <div class="form-group">
            <label>Supplier</label>
            <select name="supplier_id" class="form-control">
                <option value="">No supplier</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (string) $s['id'] === (string) ($product['supplier_id'] ?? '') ? 'selected' : '' ?>><?= e(supplier_display_name($s)) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!$suppliers): ?><div class="form-hint">No suppliers yet — add one under Suppliers first.</div><?php endif; ?>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Cost Price</label>
                <input class="form-control" type="number" step="0.01" min="0" name="cost_price" value="<?= e((string) ($product['cost_price'] ?? '0')) ?>" required>
            </div>
            <div class="form-group">
                <label>Selling Price</label>
                <input class="form-control" type="number" step="0.01" min="0" name="selling_price" value="<?= e((string) ($product['selling_price'] ?? '0')) ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Current Stock<?= $isEdit ? ' (use Adjust Stock on the list to change this)' : '' ?></label>
                <input class="form-control" type="number" min="0" name="current_stock" value="<?= e((string) ($product['current_stock'] ?? '0')) ?>" <?= $isEdit ? 'disabled' : '' ?>>
                <?php if ($isEdit): ?><input type="hidden" name="current_stock" value="<?= (int) $product['current_stock'] ?>"><?php endif; ?>
            </div>
            <div class="form-group">
                <label>Minimum Stock (low stock threshold)</label>
                <input class="form-control" type="number" min="0" name="min_stock" value="<?= e((string) ($product['min_stock'] ?? '5')) ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Expiry Date (optional)</label>
            <input class="form-control" type="date" name="expiry_date" value="<?= e($product['expiry_date'] ?? '') ?>">
        </div>
        <div class="flex gap-8 mt-16">
            <a href="<?= url('/inventory') ?>" class="btn btn-outline btn-block">Cancel</a>
            <button type="submit" class="btn btn-primary btn-block"><?= $isEdit ? 'Save Changes' : 'Add Product' ?></button>
        </div>
        <?php if ($isEdit): ?>
            <a href="<?= url('/inventory/labels?ids=' . $product['id']) ?>" class="btn btn-outline btn-block mt-8" target="_blank"><?= icon('barcode', 16) ?> Print Barcode Label</a>
        <?php endif; ?>
    </form>
</div>
