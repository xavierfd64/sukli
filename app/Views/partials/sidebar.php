<?php
/** @var string $currentPath */
/** @var array $features */
/** @var array|null $currentUser */
$role = $currentUser['role_key'] ?? null;
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="brand-icon"><?= icon('pos', 18) ?></span>
        <div>
            Sukli
            <small>A Store System</small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="<?= url('/dashboard') ?>" class="<?= active_class($currentPath, '/dashboard') ?>"><?= icon('dashboard') ?> Dashboard</a>
        <a href="<?= url('/pos') ?>" class="<?= active_class($currentPath, '/pos') ?>"><?= icon('pos') ?> POS</a>
        <a href="<?= url('/inventory') ?>" class="<?= active_class($currentPath, '/inventory') ?>"><?= icon('inventory') ?> Inventory</a>
        <a href="<?= url('/income') ?>" class="<?= active_class($currentPath, '/income') ?>"><?= icon('income') ?> Income</a>
        <a href="<?= url('/expenses') ?>" class="<?= active_class($currentPath, '/expenses') ?>"><?= icon('expense') ?> Expenses</a>
        <?php if (!empty($features['eload']['is_enabled']) && !empty($features['eload']['show_in_nav'])): ?>
        <a href="<?= url('/eload') ?>" class="<?= active_class($currentPath, '/eload') ?>"><?= icon('eload') ?> E-Load</a>
        <?php endif; ?>
        <?php if (!empty($features['gcash']['is_enabled']) && !empty($features['gcash']['show_in_nav'])): ?>
        <a href="<?= url('/gcash') ?>" class="<?= active_class($currentPath, '/gcash') ?>"><?= icon('gcash') ?> GCash</a>
        <?php endif; ?>
        <?php if (!empty($features['utang']['is_enabled']) && !empty($features['utang']['show_in_nav'])): ?>
        <a href="<?= url('/utang') ?>" class="<?= active_class($currentPath, '/utang') ?>"><?= icon('utang') ?> Utang</a>
        <?php endif; ?>

        <div class="nav-section-label">Records</div>
        <a href="<?= url('/customers') ?>" class="<?= active_class($currentPath, '/customers') ?>"><?= icon('customers') ?> Customers</a>
        <?php if (in_array($role, ['owner', 'manager'], true)): ?>
        <a href="<?= url('/suppliers') ?>" class="<?= active_class($currentPath, '/suppliers') ?>"><?= icon('suppliers') ?> Suppliers</a>
        <a href="<?= url('/reports') ?>" class="<?= active_class($currentPath, '/reports') ?>"><?= icon('reports') ?> Reports</a>
        <?php endif; ?>

        <?php if ($role === 'owner'): ?>
        <div class="nav-section-label">Administration</div>
        <a href="<?= url('/users') ?>" class="<?= active_class($currentPath, '/users') ?>"><?= icon('users') ?> Users</a>
        <a href="<?= url('/audit-log') ?>" class="<?= active_class($currentPath, '/audit-log') ?>"><?= icon('audit') ?> Audit Log</a>
        <a href="<?= url('/settings') ?>" class="<?= (str_starts_with($currentPath, '/settings') ? 'is-active' : '') ?>"><?= icon('settings') ?> Settings</a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-foot">
        <form method="post" action="<?= url('/logout') ?>">
            <?= csrf_field() ?>
            <button type="submit"><?= icon('logout', 16) ?> Logout</button>
        </form>
    </div>
</aside>
<div class="sidebar-overlay" data-toggle-sidebar></div>
