<?php
/** @var array $categories */
?>
<a href="<?= url('/settings') ?>" class="text-muted" style="font-size:12.5px;">&larr; Back to Settings</a>
<h2 style="margin:6px 0 2px;">Expense Categories</h2>
<p class="text-muted">Manage the category list used on the Expenses form.</p>

<div class="card mb-16">
    <form method="post" action="<?= url('/settings/expense-categories') ?>" class="flex gap-8">
        <?= csrf_field() ?>
        <input class="form-control" name="name" placeholder="New category name" required>
        <button type="submit" class="btn btn-outline">Add</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Category</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($categories as $c): ?>
                <tr>
                    <td><strong><?= e($c['name']) ?></strong></td>
                    <td>
                        <form method="post" action="<?= url('/settings/expense-categories/' . $c['id'] . '/delete') ?>" onsubmit="return confirm('Remove this category?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline" style="color:var(--red);">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$categories): ?><tr><td colspan="2" class="text-muted">No custom categories yet — the default list (Restock / Supplies, Utilities, Rent, Transportation, Salary, Other) is in use.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
