<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Install Sukli') ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/install.css') ?>">
</head>
<body class="install-page">
<div class="install-shell">
    <div class="install-brand">
        <span class="brand-icon"><?= icon('pos', 20) ?></span>
        <div><strong>Sukli</strong><small>A Store System — Installer</small></div>
    </div>

    <?php if (!empty($step)): ?>
    <div class="install-steps">
        <?php
        $labels = ['Welcome', 'Database', 'Administrator', 'Store', 'Install'];
        foreach ($labels as $i => $label):
            $n = $i + 1;
            $state = $n < $step ? 'is-done' : ($n === $step ? 'is-active' : '');
        ?>
            <div class="install-step <?= $state ?>">
                <span class="install-step-dot"><?= $n < $step ? icon('check', 12) : $n ?></span>
                <span class="install-step-label"><?= e($label) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="install-card">
        <?= $content ?>
    </div>
</div>
</body>
</html>
