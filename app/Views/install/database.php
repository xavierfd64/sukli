<?php
/** @var string|null $error */
/** @var array $db */
?>
<h2>Database Configuration</h2>
<p class="install-lede">Enter the database details from your hosting control panel. We'll verify the connection before you continue.</p>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<div class="alert alert-info" id="install-test-result" style="display:none;"></div>

<form method="post" action="<?= url('/install/database') ?>" id="install-db-form">
    <?= csrf_field() ?>
    <div class="form-row">
        <div class="form-group">
            <label>Database Host</label>
            <input class="form-control" type="text" name="host" value="<?= e($db['host'] ?? 'localhost') ?>" required>
        </div>
        <div class="form-group">
            <label>Database Port <span class="text-muted">(optional)</span></label>
            <input class="form-control" type="text" name="port" value="<?= e($db['port'] ?? '3306') ?>">
        </div>
    </div>
    <div class="form-group">
        <label>Database Name</label>
        <input class="form-control" type="text" name="database" value="<?= e($db['database'] ?? '') ?>" required>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Database Username</label>
            <input class="form-control" type="text" name="username" value="<?= e($db['username'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label>Database Password</label>
            <input class="form-control" type="password" name="password" value="">
        </div>
    </div>

    <div class="flex gap-8 mt-16">
        <button type="button" id="install-test-btn" class="btn btn-outline btn-block">Test Connection</button>
        <button type="submit" class="btn btn-primary btn-block">Continue</button>
    </div>
</form>

<script>window.SUKLI_CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="<?= asset('js/install.js') ?>"></script>
