<?php
/** @var array|null $role */
/** @var array $catalog */
/** @var array $allowed */
$isEdit = $role !== null;
$moduleLabels = [
    'pos' => 'POS', 'inventory' => 'Inventory', 'income' => 'Income', 'expenses' => 'Expenses',
    'eload' => 'E-Load', 'gcash' => 'GCash', 'utang' => 'Utang / Credit', 'customers' => 'Customers',
    'suppliers' => 'Suppliers', 'reports' => 'Reports', 'users' => 'Users & Roles',
    'settings' => 'Settings', 'audit_log' => 'Audit Log',
];
?>
<a href="<?= url('/roles') ?>" class="text-muted" style="font-size:12.5px;">&larr; Back to Roles</a>
<h2 style="margin:6px 0 2px;"><?= $isEdit ? 'Edit Role' : 'Add Role' ?></h2>
<p class="text-muted">Turn on only what this role actually needs. Nothing is granted by default for a new role.</p>

<form method="post" action="<?= $isEdit ? url('/roles/' . $role['id']) : url('/roles') ?>">
    <?= csrf_field() ?>

    <div class="card mb-16">
        <?php if (!$isEdit || !$role['is_system']): ?>
            <div class="form-row">
                <div class="form-group"><label>Role Name</label><input class="form-control" type="text" name="name" value="<?= e($role['name'] ?? '') ?>" required></div>
                <div class="form-group"><label>Description</label><input class="form-control" type="text" name="description" value="<?= e($role['description'] ?? '') ?>"></div>
            </div>
        <?php else: ?>
            <div class="flex items-center gap-8">
                <strong style="font-size:15px;"><?= e($role['name']) ?></strong>
                <span class="badge badge-blue">System Role</span>
            </div>
            <p class="text-muted" style="font-size:12.5px;margin-top:4px;"><?= e($role['description'] ?? '') ?> — name and description cannot be changed for a system role, but its permissions can.</p>
        <?php endif; ?>
    </div>

    <?php foreach ($catalog as $module => $rows): ?>
        <div class="card mb-16">
            <div class="card-title" style="margin-bottom:10px;"><?= e($moduleLabels[$module] ?? ucfirst($module)) ?></div>
            <div class="flex gap-16" style="flex-wrap:wrap;">
                <?php foreach ($rows as $row): ?>
                    <label class="flex items-center gap-8" style="font-weight:500;min-width:200px;">
                        <input type="checkbox" name="permissions[<?= $row['id'] ?>]" value="1" <?= !empty($allowed[$row['id']]) ? 'checked' : '' ?>>
                        <?= e($row['label']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="flex gap-8">
        <a href="<?= url('/roles') ?>" class="btn btn-outline btn-block">Cancel</a>
        <button type="submit" class="btn btn-primary btn-block"><?= $isEdit ? 'Save Permissions' : 'Create Role' ?></button>
    </div>
</form>
