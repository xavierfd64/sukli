<?php
/** @var array $admin */
/** @var array $store */
?>
<div id="install-review">
    <h2>Ready to Install</h2>
    <p class="install-lede">Review your details, then click Install Sukli to finish setup.</p>

    <div class="install-summary-row"><span>Store Name</span><span><strong><?= e($store['name'] ?? '') ?></strong></span></div>
    <div class="install-summary-row"><span>Owner Name</span><span><strong><?= e($admin['name'] ?? '') ?></strong></span></div>
    <div class="install-summary-row"><span>Username</span><span><strong><?= e($admin['username'] ?? '') ?></strong></span></div>

    <button type="button" id="install-run-btn" class="btn btn-primary btn-lg btn-block mt-16">Install Sukli</button>
    <a href="<?= url('/install/store') ?>" class="btn btn-outline btn-block mt-16">Back</a>
</div>

<div id="install-progress" style="display:none;">
    <h2>Installing Sukli...</h2>
    <div class="install-progress-track"><div class="install-progress-fill" id="install-progress-fill"></div></div>
    <ul class="install-checklist" id="install-checklist">
        <li data-key="requirements"><span class="ic-status">○</span><span>Checking system requirements</span></li>
        <li data-key="connect"><span class="ic-status">○</span><span>Connecting to database</span></li>
        <li data-key="tables"><span class="ic-status">○</span><span>Creating database tables</span></li>
        <li data-key="admin"><span class="ic-status">○</span><span>Creating administrator account</span></li>
        <li data-key="store"><span class="ic-status">○</span><span>Preparing store settings</span></li>
        <li data-key="finalize"><span class="ic-status">○</span><span>Finalizing installation</span></li>
    </ul>
    <div class="alert alert-error mt-16" id="install-progress-error" style="display:none;"></div>
</div>

<div id="install-complete" style="display:none;text-align:center;">
    <div class="install-icon-hero"><?= icon('check', 28) ?></div>
    <h2>Sukli was installed successfully!</h2>
    <p class="install-lede">Your store system is ready.</p>
    <a href="<?= url('/login') ?>" class="btn btn-primary btn-lg btn-block">Go to Login</a>
</div>

<script>window.SUKLI_CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="<?= asset('js/install.js') ?>"></script>
