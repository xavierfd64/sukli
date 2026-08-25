<?php
/** @var array $products */
/** @var array $categories */
/** @var array $stats */
/** @var int $categoryCount */
/** @var string $search */
/** @var string $categoryId */
/** @var string $filter */
/** @var int $page */
/** @var int $totalPages */
$canAdd = can('inventory', 'add');
$canEdit = can('inventory', 'edit');
$canDelete = can('inventory', 'delete');
$canManage = $canAdd || $canEdit || $canDelete;

function stock_badge(array $p): string
{
    if ((int) $p['current_stock'] <= 0) return '<span class="badge badge-red">Out of Stock</span>';
    if ((int) $p['current_stock'] <= (int) $p['min_stock']) return '<span class="badge badge-amber">Low Stock</span>';
    return '<span class="badge badge-green">In Stock</span>';
}
?>
<div class="flex items-center justify-between mb-16" style="flex-wrap:wrap;gap:10px;">
    <div>
        <h2 style="margin:0;">Inventory</h2>
        <p class="text-muted" style="margin:0;">Manage your products and stock</p>
    </div>
    <?php if ($canManage): ?>
    <div class="flex gap-8">
        <?php if ($canEdit): ?>
        <button type="button" class="btn btn-outline" data-modal-target="#import-modal"><?= icon('archive', 16) ?> Import</button>
        <a href="<?= url('/inventory/export.csv?' . http_build_query(['q' => $search])) ?>" class="btn btn-outline"><?= icon('reports', 16) ?> Export</a>
        <a href="<?= url('/inventory/labels') ?>" class="btn btn-outline" target="_blank"><?= icon('barcode', 16) ?> Print Labels</a>
        <a href="<?= url('/inventory/categories') ?>" class="btn btn-outline"><?= icon('settings', 16) ?> Categories</a>
        <?php endif; ?>
        <?php if ($canAdd): ?>
        <a href="<?= url('/inventory/create') ?>" class="btn btn-primary"><?= icon('plus', 16) ?> Add Product</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="grid grid-4 mb-16">
    <div class="card"><div class="text-muted" style="font-size:11.5px;font-weight:700;text-transform:uppercase;">Total Products</div><div style="font-size:22px;font-weight:700;"><?= (int) $stats['total_products'] ?></div></div>
    <div class="card"><div class="text-muted" style="font-size:11.5px;font-weight:700;text-transform:uppercase;">Stock Value</div><div style="font-size:22px;font-weight:700;"><?= money($stats['stock_value']) ?></div></div>
    <div class="card"><div class="text-muted" style="font-size:11.5px;font-weight:700;text-transform:uppercase;">Low Stock</div><div style="font-size:22px;font-weight:700;color:var(--amber);"><?= (int) $stats['low_stock'] ?></div></div>
    <div class="card"><div class="text-muted" style="font-size:11.5px;font-weight:700;text-transform:uppercase;">Out of Stock</div><div style="font-size:22px;font-weight:700;color:var(--red);"><?= (int) $stats['out_of_stock'] ?></div></div>
</div>

<div class="section-tabs">
    <a href="<?= url('/inventory') ?>" class="<?= $filter === '' ? 'is-active' : '' ?>">All Products</a>
    <a href="<?= url('/inventory?filter=low_stock') ?>" class="<?= $filter === 'low_stock' ? 'is-active' : '' ?>">Low Stock</a>
    <a href="<?= url('/inventory?filter=out_of_stock') ?>" class="<?= $filter === 'out_of_stock' ? 'is-active' : '' ?>">Out of Stock</a>
    <a href="<?= url('/inventory?filter=expiring_soon') ?>" class="<?= $filter === 'expiring_soon' ? 'is-active' : '' ?>">Expiring Soon</a>
</div>

<div class="card mb-16">
    <form method="get" action="<?= url('/inventory') ?>" class="flex gap-12" style="flex-wrap:wrap;align-items:flex-end;">
        <?php if ($filter): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
        <div style="flex:2;min-width:200px;">
            <label>Search</label>
            <input class="form-control" type="text" name="q" value="<?= e($search) ?>" placeholder="Search product or scan barcode">
        </div>
        <div style="flex:1;min-width:160px;">
            <label>Category</label>
            <select name="category_id" class="form-control">
                <option value="">All Categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (string) $c['id'] === (string) $categoryId ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-outline"><?= icon('search', 16) ?> Filter</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th></th><th>Product</th><th>Category</th><th>Barcode</th><th>Cost</th><th>Selling Price</th><th>Stock</th><th>Status</th><?php if ($canManage): ?><th>Actions</th><?php endif; ?></tr>
            </thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td>
                        <?php if (\Sukli\Services\UploadService::exists($p['image_path'] ?? null)): ?>
                            <img src="<?= e(\Sukli\Services\UploadService::url($p['image_path'])) ?>" alt="" class="product-thumb">
                        <?php else: ?>
                            <div class="product-thumb product-thumb-placeholder">NO<br>IMAGE</div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= e($p['name']) ?></strong></td>
                    <td class="text-muted"><?= e($p['category_name'] ?? '—') ?></td>
                    <td class="text-muted"><?= e($p['barcode'] ?? '—') ?></td>
                    <td><?= money($p['cost_price']) ?></td>
                    <td><?= money($p['selling_price']) ?></td>
                    <td><?= (int) $p['current_stock'] ?> <?= e($p['unit']) ?></td>
                    <td><?= stock_badge($p) ?></td>
                    <?php if ($canManage): ?>
                    <td>
                        <div class="flex gap-8">
                            <?php if ($canEdit): ?>
                            <a href="<?= url('/inventory/' . $p['id'] . '/edit') ?>" class="btn btn-sm btn-outline"><?= icon('edit', 14) ?></a>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                            <form method="post" action="<?= url('/inventory/' . $p['id'] . '/archive') ?>" onsubmit="return confirm('<?= $p['status'] === 'active' ? 'Archive' : 'Restore' ?> this product?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline"><?= icon('archive', 14) ?></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($canEdit): ?>
                            <button type="button" class="btn btn-sm btn-outline" data-modal-target="#adjust-<?= $p['id'] ?>"><?= icon('barcode', 14) ?></button>
                            <a href="<?= url('/inventory/labels?ids=' . $p['id']) ?>" class="btn btn-sm btn-outline" target="_blank" title="Print Label"><?= icon('reports', 14) ?></a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$products): ?><tr><td colspan="9" class="text-muted">No products found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="flex gap-8 mt-16">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>" href="<?= url('/inventory?page=' . $i . ($search ? '&q=' . urlencode($search) : '') . ($filter ? '&filter=' . $filter : '')) ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($canEdit): ?>
<div class="modal-backdrop" id="import-modal">
    <div class="modal">
        <h3>Import Products</h3>
        <p class="text-muted">CSV columns: name, barcode, category, cost_price, selling_price, current_stock, min_stock, unit. Existing products are matched by barcode.</p>
        <form method="post" action="<?= url('/inventory/import') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>CSV File</label>
                <input type="file" name="file" accept=".csv" class="form-control" required>
            </div>
            <div class="flex gap-8">
                <button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary btn-block">Import</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php foreach ($products as $p): ?>
<div class="modal-backdrop" id="adjust-<?= $p['id'] ?>">
    <div class="modal">
        <h3>Adjust Stock — <?= e($p['name']) ?></h3>
        <p class="text-muted">Current stock: <?= (int) $p['current_stock'] ?> <?= e($p['unit']) ?></p>
        <form method="post" action="<?= url('/inventory/' . $p['id'] . '/adjust') ?>">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Direction</label>
                    <select name="direction" class="form-control">
                        <option value="in">Stock In (+)</option>
                        <option value="out">Stock Out (-)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" min="1" name="quantity" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label>Note</label>
                <input type="text" name="note" class="form-control" placeholder="e.g. Restock from supplier">
            </div>
            <div class="flex gap-8">
                <button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary btn-block">Save</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>
