<?php
/** @var array $store */
/** @var array $categories */
/** @var array $summary */
/** @var string $phpVersion */
/** @var bool $autoPrintReceipt */
?>
<h2 style="margin-top:0;">Settings</h2>
<p class="text-muted">Manage your store, system preferences and more.</p>

<div class="grid settings-layout">
<div>

    <div class="card mb-16" style="border-left:4px solid var(--purple);">
        <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:10px;">
            <div>
                <div class="card-title" style="margin:0;">Feature Management</div>
                <div class="text-muted" style="font-size:12.5px;">Enable or disable features like E-Load, GCash and Utang. Control visibility in POS and Dashboard.</div>
            </div>
            <a href="<?= url('/settings/features') ?>" class="btn btn-purple">Manage Features <?= icon('chevron-right', 14) ?></a>
        </div>
    </div>

    <div class="card mb-16" style="border-left:4px solid var(--green);">
        <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:10px;">
            <div>
                <div class="card-title" style="margin:0;">Payment Methods</div>
                <div class="text-muted" style="font-size:12.5px;">Enable or disable Cash, GCash, Utang, E-Wallet, Bank Transfer and Other for POS and split payments.</div>
            </div>
            <a href="<?= url('/settings/payment-methods') ?>" class="btn btn-outline">Manage Payment Methods <?= icon('chevron-right', 14) ?></a>
        </div>
    </div>

    <div class="card mb-16">
        <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:10px;">
            <div>
                <div class="card-title" style="margin:0;">E-Load Networks</div>
                <div class="text-muted" style="font-size:12.5px;">Manage the network list used on the E-Load form (Globe, Smart, TNT, DITO, ...).</div>
            </div>
            <a href="<?= url('/settings/networks') ?>" class="btn btn-outline">Manage Networks <?= icon('chevron-right', 14) ?></a>
        </div>
    </div>

    <div class="card mb-16">
        <div class="card-title">General Settings</div>
        <div class="card-subtitle">Store information, business preferences, receipt footer</div>
        <form method="post" action="<?= url('/settings/general') ?>">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group"><label>Store Name</label><input class="form-control" name="name" value="<?= e($store['name']) ?>" required></div>
                <div class="form-group"><label>Phone</label><input class="form-control" name="phone" value="<?= e($store['phone'] ?? '') ?>"></div>
            </div>
            <div class="form-group"><label>Address</label><input class="form-control" name="address" value="<?= e($store['address'] ?? '') ?>"></div>
            <div class="form-row">
                <div class="form-group"><label>Currency Symbol</label><input class="form-control" name="currency_symbol" value="<?= e($store['currency_symbol']) ?>"></div>
                <div class="form-group"><label>Tax Rate (%)</label><input class="form-control" type="number" step="0.01" min="0" name="tax_rate" value="<?= e((string) $store['tax_rate']) ?>"></div>
            </div>
            <div class="form-group">
                <label>Timezone</label>
                <select class="form-control" name="timezone">
                    <?php foreach ($timezones as $tz): ?>
                        <option value="<?= e($tz) ?>" <?= $tz === $store['timezone'] ? 'selected' : '' ?>><?= e($tz) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint">Used for "today", reports, and all recorded timestamps throughout Sukli.</div>
            </div>
            <div class="form-group"><label>Receipt Footer</label><input class="form-control" name="receipt_footer" value="<?= e($store['receipt_footer'] ?? '') ?>"></div>
            <label class="flex items-center gap-8 mb-16" style="font-weight:500;">
                <input type="checkbox" name="auto_print_receipt" value="1" <?= $autoPrintReceipt ? 'checked' : '' ?> style="width:18px;height:18px;">
                Auto Print Receipt after checkout
            </label>
            <button type="submit" class="btn btn-primary">Save General Settings</button>
        </form>
    </div>

    <div class="card mb-16">
        <div class="card-title">Categories</div>
        <div class="card-subtitle">Manage product categories</div>
        <div class="flex gap-8 mb-16" style="flex-wrap:wrap;">
            <?php foreach ($categories as $c): ?><span class="badge badge-blue"><?= e($c['name']) ?></span><?php endforeach; ?>
            <?php if (!$categories): ?><span class="text-muted">No categories yet.</span><?php endif; ?>
        </div>
        <form method="post" action="<?= url('/inventory/categories') ?>" class="flex gap-8">
            <?= csrf_field() ?>
            <input class="form-control" name="name" placeholder="New category name" required>
            <button type="submit" class="btn btn-outline">Add</button>
        </form>
    </div>

    <div class="card mb-16">
        <div class="card-title">Security</div>
        <div class="card-subtitle">Change your account password</div>
        <form method="post" action="<?= url('/settings/security') ?>">
            <?= csrf_field() ?>
            <div class="form-group"><label>Current Password</label><input class="form-control" type="password" name="current_password" required></div>
            <div class="form-row">
                <div class="form-group"><label>New Password</label><input class="form-control" type="password" name="new_password" minlength="8" required></div>
                <div class="form-group"><label>Confirm New Password</label><input class="form-control" type="password" name="confirm_password" minlength="8" required></div>
            </div>
            <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
    </div>

    <div class="card">
        <div class="card-title">Backup &amp; Restore</div>
        <div class="card-subtitle">Download a data backup. To restore, import it via your hosting control panel's phpMyAdmin.</div>
        <a href="<?= url('/settings/backup') ?>" class="btn btn-outline"><?= icon('archive', 16) ?> Download Backup (.sql)</a>
    </div>

</div>

<div>
    <div class="card mb-16">
        <div class="card-title">Business Summary</div>
        <div class="flex items-center justify-between" style="font-size:13px;margin-bottom:8px;"><span class="text-muted">Total Sales (Today)</span><strong><?= money($summary['sales_today']) ?></strong></div>
        <div class="flex items-center justify-between" style="font-size:13px;margin-bottom:8px;"><span class="text-muted">Total Income (Today)</span><strong><?= money($summary['income_today']) ?></strong></div>
        <div class="flex items-center justify-between" style="font-size:13px;margin-bottom:8px;"><span class="text-muted">Expenses (Today)</span><strong><?= money($summary['expense_today']) ?></strong></div>
        <div class="flex items-center justify-between" style="font-size:13px;"><span class="text-muted">Net Income (Today)</span><strong><?= money((float) $summary['sales_today'] + (float) $summary['income_today'] - (float) $summary['expense_today']) ?></strong></div>
    </div>
    <div class="card">
        <div class="card-title">About System</div>
        <div class="flex items-center justify-between" style="font-size:12.5px;margin-bottom:6px;"><span class="text-muted">System Name</span><span>Sukli — A Store System</span></div>
        <div class="flex items-center justify-between" style="font-size:12.5px;margin-bottom:6px;"><span class="text-muted">Version</span><span>1.0.0</span></div>
        <div class="flex items-center justify-between" style="font-size:12.5px;margin-bottom:6px;"><span class="text-muted">PHP Version</span><span><?= e($phpVersion) ?></span></div>
        <div class="flex items-center justify-between" style="font-size:12.5px;"><span class="text-muted">Database</span><span class="badge badge-green">Connected</span></div>
    </div>
</div>
</div>
