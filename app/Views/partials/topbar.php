<?php
/** @var array|null $currentUser */
/** @var string $pageTitle */
/** @var array $branchSwitcherStores */
/** @var array|null $branchSwitcherCurrent */
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
        <?php if (!empty($branchSwitcherStores)): ?>
        <form method="post" action="<?= url('/branches/switch') ?>" class="branch-switcher">
            <?= csrf_field() ?>
            <select name="store_id" onchange="this.form.submit()" aria-label="Switch branch">
                <?php foreach ($branchSwitcherStores as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= ($branchSwitcherCurrent && (int) $branchSwitcherCurrent['id'] === (int) $s['id']) ? 'selected' : '' ?>>
                        <?= e($s['name']) ?><?= $s['is_main_branch'] ? ' (Main)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
        <div class="user-chip">
            <div class="user-avatar"><?= e($initials ?: 'S') ?></div>
            <div class="user-meta">
                <div class="user-name"><?= e($currentUser['name'] ?? 'User') ?></div>
                <div class="user-role"><?= e($currentUser['role_name'] ?? '') ?></div>
            </div>
        </div>
    </div>
</header>
