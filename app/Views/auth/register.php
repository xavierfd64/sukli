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

        <h2 style="margin:0 0 4px;font-size:18px;">Start your free trial</h2>
        <p class="text-muted" style="margin:0 0 16px;font-size:12.5px;">14 days, full access, no card required.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= url('/register') ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="business_name">Business Name</label>
                <input class="form-control" type="text" id="business_name" name="business_name" placeholder="Juan's Sari-Sari Store" required autofocus>
            </div>
            <div class="form-group">
                <label for="owner_name">Your Full Name</label>
                <input class="form-control" type="text" id="owner_name" name="owner_name" required>
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input class="form-control" type="text" id="username" name="username" autocomplete="username" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email" autocomplete="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input class="form-control" type="password" id="password" name="password" autocomplete="new-password" minlength="8" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Create Account</button>
        </form>

        <p class="text-muted" style="text-align:center;font-size:12.5px;margin-top:16px;">
            Already have an account? <a href="<?= url('/login') ?>">Sign in</a>
        </p>
    </div>
</div>
