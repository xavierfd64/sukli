<?php
/** @var string|null $error */
/** @var array $admin */
?>
<h2>Create Administrator Account</h2>
<p class="install-lede">This account will have full Owner access to Sukli.</p>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" action="<?= url('/install/admin') ?>">
    <?= csrf_field() ?>
    <div class="form-group">
        <label>Owner Name</label>
        <input class="form-control" type="text" name="name" value="<?= e($admin['name'] ?? '') ?>" placeholder="Juan Dela Cruz" required autofocus>
    </div>
    <div class="form-group">
        <label>Email Address</label>
        <input class="form-control" type="email" name="email" value="<?= e($admin['email'] ?? '') ?>" placeholder="owner@email.com">
    </div>
    <div class="form-group">
        <label>Username</label>
        <input class="form-control" type="text" name="username" value="<?= e($admin['username'] ?? '') ?>" placeholder="admin" required>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Password</label>
            <input class="form-control" type="password" name="password" minlength="8" required>
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <input class="form-control" type="password" name="confirm_password" minlength="8" required>
        </div>
    </div>
    <div class="form-hint mb-16">At least 8 characters.</div>

    <div class="flex gap-8">
        <a href="<?= url('/install/database') ?>" class="btn btn-outline btn-block">Back</a>
        <button type="submit" class="btn btn-primary btn-block">Continue</button>
    </div>
</form>
