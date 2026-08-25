<?php
/** @var array $categories */
?>
<a href="<?= url('/inventory') ?>" class="text-muted" style="font-size:12.5px;">&larr; Back to Inventory</a>
<h2 style="margin:6px 0 2px;">Product Categories</h2>
<p class="text-muted">Categories used when adding/editing products and filtering inventory.</p>

<div class="card mb-16">
    <form method="post" action="<?= url('/inventory/categories') ?>" class="flex gap-8">
        <?= csrf_field() ?>
        <input class="form-control" name="name" placeholder="New category name" required>
        <button type="submit" class="btn btn-outline">Add</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Category</th><th>Products</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($categories as $c): ?>
                <tr>
                    <td>
                        <form method="post" action="<?= url('/inventory/categories/' . $c['id']) ?>" class="flex gap-8" style="max-width:320px;">
                            <?= csrf_field() ?>
                            <input class="form-control" name="name" value="<?= e($c['name']) ?>" required>
                            <button type="submit" class="btn btn-sm btn-outline">Save</button>
                        </form>
                    </td>
                    <td class="text-muted"><?= (int) $c['product_count'] ?></td>
                    <td>
                        <form method="post" action="<?= url('/inventory/categories/' . $c['id'] . '/delete') ?>"
                              onsubmit="return confirm('Delete this category?<?= $c['product_count'] > 0 ? ' ' . $c['product_count'] . ' product(s) will become Uncategorized.' : '' ?>');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline" style="color:var(--red);"><?= icon('trash', 14) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$categories): ?><tr><td colspan="3" class="text-muted">No categories yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
