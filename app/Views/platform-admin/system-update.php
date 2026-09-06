<?php
/** @var string $currentVersion */
/** @var string $phpVersion */
/** @var array|null $result */
/** @var string|null $uploadedName */
?>
<h2 style="margin-top:0;">System Update</h2>
<p class="text-muted">Upload an update package to validate it against this installation. This build only ever validates and, for a package explicitly marked as a test, extracts it into a quarantine folder — it never modifies any live application file.</p>

<div class="grid grid-2 mb-16">
    <div class="card">
        <div class="card-title">This Installation</div>
        <div class="text-muted" style="font-size:12.5px;">Application Version</div>
        <div style="font-weight:700;margin-bottom:8px;"><?= e($currentVersion) ?></div>
        <div class="text-muted" style="font-size:12.5px;">PHP Version</div>
        <div style="font-weight:700;"><?= e($phpVersion) ?></div>
    </div>
    <div class="card">
        <div class="card-title">Upload Update ZIP</div>
        <form method="post" action="<?= url('/platform-admin/system-update') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group">
                <input class="form-control" type="file" name="package" accept=".zip,application/zip" required>
                <div class="form-hint">Max 10MB. Must contain an update.json manifest at the package root.</div>
            </div>
            <button type="submit" class="btn btn-primary">Upload &amp; Validate</button>
        </form>
    </div>
</div>

<?php if ($result !== null): ?>
<div class="card">
    <div class="card-title">Update Package Detected</div>
    <div class="grid grid-2 mb-16" style="font-size:12.5px;">
        <div>
            <div class="text-muted">Package</div>
            <div style="font-weight:700;"><?= e($uploadedName ?? '—') ?></div>
        </div>
        <div>
            <div class="text-muted">Version</div>
            <div style="font-weight:700;"><?= e($result['manifest']['version'] ?? '—') ?></div>
        </div>
        <div>
            <div class="text-muted">Type</div>
            <div style="font-weight:700;"><?= e($result['manifest']['type'] ?? '—') ?></div>
        </div>
        <div>
            <div class="text-muted">Mode</div>
            <div style="font-weight:700;">SAFE TEST / DRY RUN</div>
        </div>
    </div>

    <div class="card-title">System Update Test</div>
    <table class="table mb-16">
        <tbody>
        <?php foreach ($result['steps'] as $step): ?>
            <tr>
                <td style="width:28px;"><?= $step['ok'] ? '<span style="color:var(--green-dark);font-weight:700;">&#10003;</span>' : '<span style="color:var(--red);font-weight:700;">&#10007;</span>' ?></td>
                <td style="font-weight:600;white-space:nowrap;"><?= e($step['label']) ?></td>
                <td class="text-muted"><?= e($step['detail']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($result['ok']): ?>
        <div class="alert alert-success">
            <strong>RESULT: UPDATE SYSTEM IS WORKING.</strong>
            The package was validated and its declared files were extracted to a quarantine folder under storage/updates/ for inspection. No application file, tenant data, user account, subscription, or payment was modified.
        </div>
    <?php else: ?>
        <?php $failedStep = end($result['steps']); ?>
        <div class="alert alert-error">
            <strong>SYSTEM UPDATE TEST FAILED</strong><br>
            Step: <?= e($failedStep['label']) ?><br>
            Reason: <?= e($failedStep['detail']) ?><br>
            No production data was modified.
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
