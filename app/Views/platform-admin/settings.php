<?php
/** @var int $trialDays */
/** @var string $platformName */
?>
<h2 style="margin-top:0;">Platform Settings</h2>
<p class="text-muted">Global settings for the whole platform — not tied to any single organization.</p>

<div class="card" style="max-width:480px;">
    <form method="post" action="<?= url('/platform-admin/settings') ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Platform Name</label>
            <input class="form-control" name="platform_name" value="<?= e($platformName) ?>" required>
            <div class="form-hint">Shown on the login/registration pages and platform emails.</div>
        </div>
        <div class="form-group">
            <label>Free Trial Length (days)</label>
            <input class="form-control" type="number" min="1" name="trial_days" value="<?= (int) $trialDays ?>" required>
            <div class="form-hint">Applied to every new organization at registration or fresh install.</div>
        </div>
        <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
</div>
