<?php
/** @var array $methods */
$labels = [
    'cash' => 'Accepted at POS as immediate payment.',
    'gcash' => 'Also requires the GCash module to be enabled in Feature Management.',
    'utang' => 'Also requires the Utang module to be enabled in Feature Management.',
    'ewallet' => 'A general e-wallet option (Maya, GrabPay, etc.) for POS and Utang payments.',
    'bank_transfer' => 'For customers paying via bank transfer.',
    'other' => 'A catch-all for any other payment arrangement.',
];
?>
<a href="<?= url('/settings') ?>" class="text-muted" style="font-size:12.5px;">&larr; Back to Settings</a>
<h2 style="margin:6px 0 2px;">Payment Methods</h2>
<p class="text-muted">Control which payment methods are available at POS, in split payments, and in the Utang payment dialog.</p>

<form method="post" action="<?= url('/settings/payment-methods') ?>">
    <?= csrf_field() ?>
    <?php foreach ($methods as $key => $m): ?>
        <div class="card mb-16">
            <div class="flex items-center justify-between mb-16" style="flex-wrap:wrap;gap:10px;">
                <div style="flex:1;min-width:220px;">
                    <div class="form-group" style="margin-bottom:6px;">
                        <input class="form-control" name="methods[<?= $key ?>][name]" value="<?= e($m['name']) ?>" style="font-weight:600;">
                    </div>
                    <div class="text-muted" style="font-size:12px;"><?= e($labels[$key] ?? '') ?></div>
                </div>
                <label class="flex items-center gap-8">
                    <input type="checkbox" name="methods[<?= $key ?>][enabled]" value="1" <?= $m['is_enabled'] ? 'checked' : '' ?> style="width:18px;height:18px;">
                    <strong><?= $m['is_enabled'] ? 'Enabled' : 'Disabled' ?></strong>
                </label>
            </div>
        </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary btn-lg">Save Payment Methods</button>
</form>
