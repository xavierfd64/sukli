<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($pageTitle ?? 'Platform Admin') . ' — ' . ($platformName ?? 'Sukli')) ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>:root{--accent:<?= e($themeColor ?? '#16a34a') ?>;--font-family:<?= e(\Sukli\Services\PlatformSettingsService::FONT_CHOICES[$themeFont ?? 'system']['stack'] ?? \Sukli\Services\PlatformSettingsService::FONT_CHOICES['system']['stack']) ?>;}</style>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-icon"><?= icon('settings', 18) ?></span>
            <div>
                Sukli
                <small>Platform Admin</small>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="<?= url('/platform-admin') ?>" class="<?= active_class($currentPath, '/platform-admin') ?>"><?= icon('dashboard') ?> Dashboard</a>
            <a href="<?= url('/platform-admin/organizations') ?>" class="<?= active_class($currentPath, '/platform-admin/organizations') ?>"><?= icon('customers') ?> Organizations</a>
            <a href="<?= url('/platform-admin/plans') ?>" class="<?= active_class($currentPath, '/platform-admin/plans') ?>"><?= icon('wallet') ?> Plans</a>
            <a href="<?= url('/platform-admin/payments') ?>" class="<?= active_class($currentPath, '/platform-admin/payments') ?>"><?= icon('cash') ?> Payments</a>
            <a href="<?= url('/platform-admin/settings') ?>" class="<?= active_class($currentPath, '/platform-admin/settings') ?>"><?= icon('settings') ?> Settings</a>
            <a href="<?= url('/platform-admin/system-update') ?>" class="<?= active_class($currentPath, '/platform-admin/system-update') ?>"><?= icon('archive') ?> System Update</a>

            <div class="nav-section-label">&nbsp;</div>
            <a href="<?= url('/dashboard') ?>"><?= icon('chevron-right') ?> Back to My Store</a>
        </nav>
    </aside>
    <div class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="hamburger" data-toggle-sidebar aria-label="Toggle menu"><?= icon('menu', 22) ?></button>
                <h1><?= e($pageTitle ?? 'Platform Admin') ?></h1>
            </div>
            <div class="topbar-right">
                <div class="user-chip">
                    <div class="user-avatar">SA</div>
                    <div class="user-meta">
                        <div class="user-name"><?= e($currentUser['name'] ?? 'Platform Admin') ?></div>
                        <div class="user-role">Platform Super Admin</div>
                    </div>
                </div>
                <form method="post" action="<?= url('/logout') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-sm"><?= icon('logout', 14) ?> Logout</button>
                </form>
            </div>
        </header>
        <div class="content">
            <?php $error = flash_get('error'); $success = flash_get('success'); ?>
            <?php if ($error): ?><div class="alert alert-error" data-flash><?= e($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success" data-flash><?= e($success) ?></div><?php endif; ?>
            <?= $content ?>
        </div>
    </div>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
