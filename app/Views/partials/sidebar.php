<?php
/** @var string $currentPath */
/** @var array $features */
/** @var array|null $currentUser */
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
        <?php if (can('pos', 'view')): ?>
        <a href="<?= url('/pos') ?>" class="<?= active_class($currentPath, '/pos') ?>"><?= icon('pos') ?> POS</a>
        <?php endif; ?>
        <?php if (can('inventory', 'view')): ?>
        <a href="<?= url('/inventory') ?>" class="<?= active_class($currentPath, '/inventory') ?>"><?= icon('inventory') ?> Inventory</a>
        <?php endif; ?>
        <?php if (can('income', 'view')): ?>
        <a href="<?= url('/income') ?>" class="<?= active_class($currentPath, '/income') ?>"><?= icon('income') ?> Income</a>
        <?php endif; ?>
        <?php if (can('expenses', 'view')): ?>
        <a href="<?= url('/expenses') ?>" class="<?= active_class($currentPath, '/expenses') ?>"><?= icon('expense') ?> Expenses</a>
        <?php endif; ?>
        <?php if (!empty($features['eload']['is_enabled']) && !empty($features['eload']['show_in_nav']) && can('eload', 'view')): ?>
        <a href="<?= url('/eload') ?>" class="<?= active_class($currentPath, '/eload') ?>"><?= icon('eload') ?> E-Load</a>
        <?php endif; ?>
        <?php if (!empty($features['gcash']['is_enabled']) && !empty($features['gcash']['show_in_nav']) && can('gcash', 'view')): ?>
        <a href="<?= url('/gcash') ?>" class="<?= active_class($currentPath, '/gcash') ?>"><?= icon('gcash') ?> GCash</a>
        <?php endif; ?>
        <?php if (!empty($features['utang']['is_enabled']) && !empty($features['utang']['show_in_nav']) && can('utang', 'view')): ?>
        <a href="<?= url('/utang') ?>" class="<?= active_class($currentPath, '/utang') ?>"><?= icon('utang') ?> Utang</a>
        <?php endif; ?>

        <?php if (can('customers', 'view') || can('suppliers', 'view') || can('reports', 'view')): ?>
        <div class="nav-section-label">Records</div>
        <?php endif; ?>
        <?php if (can('customers', 'view')): ?>
        <a href="<?= url('/customers') ?>" class="<?= active_class($currentPath, '/customers') ?>"><?= icon('customers') ?> Customers</a>
        <?php endif; ?>
        <?php if (can('suppliers', 'view')): ?>
        <a href="<?= url('/suppliers') ?>" class="<?= active_class($currentPath, '/suppliers') ?>"><?= icon('suppliers') ?> Suppliers</a>
        <?php endif; ?>
        <?php if (can('reports', 'view')): ?>
        <a href="<?= url('/reports') ?>" class="<?= active_class($currentPath, '/reports') ?>"><?= icon('reports') ?> Reports</a>
        <?php endif; ?>

        <?php if (can('users', 'manage') || can('audit_log', 'view') || can('settings', 'manage')): ?>
        <div class="nav-section-label">Administration</div>
        <?php endif; ?>
        <?php if (can('users', 'manage')): ?>
        <a href="<?= url('/users') ?>" class="<?= active_class($currentPath, '/users') ?>"><?= icon('users') ?> Users</a>
        <?php endif; ?>
        <?php if (can('settings', 'manage')): ?>
        <a href="<?= url('/branches') ?>" class="<?= active_class($currentPath, '/branches') ?>"><?= icon('suppliers') ?> Branches</a>
        <?php endif; ?>
        <?php if (can('audit_log', 'view')): ?>
        <a href="<?= url('/audit-log') ?>" class="<?= active_class($currentPath, '/audit-log') ?>"><?= icon('audit') ?> Audit Log</a>
        <?php endif; ?>
        <?php if (can('settings', 'manage')): ?>
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
