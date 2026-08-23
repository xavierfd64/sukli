<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Sukli') ?> — Sukli</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?= view_partial('partials/sidebar', get_defined_vars()) ?>
    <div class="main">
        <?= view_partial('partials/topbar', get_defined_vars()) ?>
        <div class="content">
            <?php $error = flash_get('error'); $success = flash_get('success'); ?>
            <?php if ($error): ?><div class="alert alert-error" data-flash><?= e($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success" data-flash><?= e($success) ?></div><?php endif; ?>
            <?= $content ?>
        </div>
    </div>
    <?= view_partial('partials/bottomnav', get_defined_vars()) ?>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
