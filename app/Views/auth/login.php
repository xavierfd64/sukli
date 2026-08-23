<?php /** @var string|null $error */ ?>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="brand-icon" style="color:#fff;"><?= icon('pos', 20) ?></span>
            <div>
                <strong>Sukli</strong>
                <small>A Store System</small>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= url('/login') ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="username">Username</label>
                <input class="form-control" type="text" id="username" name="username" autocomplete="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Sign In</button>
        </form>
    </div>
</div>
