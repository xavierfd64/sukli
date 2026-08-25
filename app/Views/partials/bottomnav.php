<?php /** @var string $currentPath */ ?>
<nav class="bottom-nav">
    <a href="<?= url('/dashboard') ?>" class="<?= active_class($currentPath, '/dashboard') ?>"><?= icon('dashboard', 20) ?><span>Dashboard</span></a>
    <?php if (can('pos', 'view')): ?>
    <a href="<?= url('/pos') ?>" class="<?= active_class($currentPath, '/pos') ?>"><?= icon('pos', 20) ?><span>POS</span></a>
    <?php endif; ?>
    <?php if (can('inventory', 'view')): ?>
    <a href="<?= url('/inventory') ?>" class="<?= active_class($currentPath, '/inventory') ?>"><?= icon('inventory', 20) ?><span>Inventory</span></a>
    <?php endif; ?>
    <button type="button" class="more-btn" data-toggle-sidebar><?= icon('more', 20) ?><span>More</span></button>
</nav>
