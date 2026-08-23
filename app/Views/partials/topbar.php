<?php
/** @var array|null $currentUser */
/** @var string $pageTitle */
$initials = '';
if (!empty($currentUser['name'])) {
    $parts = preg_split('/\s+/', trim($currentUser['name']));
    $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
}
?>
<header class="topbar">
    <div class="topbar-left">
        <button type="button" class="hamburger" data-toggle-sidebar aria-label="Toggle menu"><?= icon('menu', 22) ?></button>
        <h1><?= e($pageTitle ?? 'Sukli') ?></h1>
    </div>
    <div class="topbar-right">
        <div class="user-chip">
            <div class="user-avatar"><?= e($initials ?: 'S') ?></div>
            <div class="user-meta">
                <div class="user-name"><?= e($currentUser['name'] ?? 'User') ?></div>
                <div class="user-role"><?= e($currentUser['role_name'] ?? '') ?></div>
            </div>
        </div>
    </div>
</header>
