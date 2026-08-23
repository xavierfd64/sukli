<?php
/** @var array $features */
$labels = [
    'eload' => ['E-Load', 'Recording-only E-Load transactions.'],
    'gcash' => ['GCash', 'Cash-In / Cash-Out recording, and GCash as a POS payment option.'],
    'utang' => ['Utang / Credit', 'Store credit sales at POS and the Utang module.'],
];
?>
<a href="<?= url('/settings') ?>" class="text-muted" style="font-size:12.5px;">&larr; Back to Settings</a>
<h2 style="margin:6px 0 2px;">Feature Management</h2>
<p class="text-muted">Disabling a feature hides it from navigation, POS, and the dashboard — historical records are always preserved.</p>

<form method="post" action="<?= url('/settings/features') ?>">
    <?= csrf_field() ?>
    <?php foreach ($labels as $key => [$title, $desc]): $f = $features[$key]; ?>
        <div class="card mb-16">
            <div class="flex items-center justify-between mb-16">
                <div>
                    <div class="card-title" style="margin:0;"><?= e($title) ?></div>
                    <div class="text-muted" style="font-size:12.5px;"><?= e($desc) ?></div>
                </div>
                <label class="flex items-center gap-8">
                    <input type="checkbox" name="features[<?= $key ?>][enabled]" value="1" <?= $f['is_enabled'] ? 'checked' : '' ?> style="width:18px;height:18px;">
                    <strong><?= $f['is_enabled'] ? 'Enabled' : 'Disabled' ?></strong>
                </label>
            </div>
            <div class="flex gap-16" style="flex-wrap:wrap;">
                <label class="flex items-center gap-8" style="font-weight:500;">
                    <input type="checkbox" name="features[<?= $key ?>][show_in_nav]" value="1" <?= $f['show_in_nav'] ? 'checked' : '' ?>>
                    Show in Navigation
                </label>
                <label class="flex items-center gap-8" style="font-weight:500;">
                    <input type="checkbox" name="features[<?= $key ?>][show_in_dashboard]" value="1" <?= $f['show_in_dashboard'] ? 'checked' : '' ?>>
                    Show in Dashboard
                </label>
            </div>
        </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary btn-lg">Save Feature Settings</button>
</form>
