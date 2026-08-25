<?php
/** @var array $store */
/** @var array $categories */
/** @var array $summary */
/** @var string $phpVersion */
/** @var bool $autoPrintReceipt */
/** @var string $receiptHeader */
/** @var bool $receiptShowAddress */
/** @var bool $receiptShowPhone */
/** @var bool $receiptShowLogo */
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
        <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:10px;">
            <div>
                <div class="card-title" style="margin:0;">E-Load Products</div>
                <div class="text-muted" style="font-size:12.5px;">Manage load products per network — cost, selling price, and earnings.</div>
            </div>
            <a href="<?= url('/settings/eload-products') ?>" class="btn btn-outline">Manage Products <?= icon('chevron-right', 14) ?></a>
        </div>
    </div>

    <div class="card mb-16">
        <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:10px;">
            <div>
                <div class="card-title" style="margin:0;">GCash Charge Brackets</div>
                <div class="text-muted" style="font-size:12.5px;">Set the amount ranges the GCash form uses to auto-suggest a service charge.</div>
            </div>
            <a href="<?= url('/settings/gcash-brackets') ?>" class="btn btn-outline">Manage Brackets <?= icon('chevron-right', 14) ?></a>
        </div>
    </div>

    <div class="card mb-16">
        <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:10px;">
            <div>
                <div class="card-title" style="margin:0;">Expense Categories</div>
                <div class="text-muted" style="font-size:12.5px;">Manage the category list used on the Expenses form.</div>
            </div>
            <a href="<?= url('/settings/expense-categories') ?>" class="btn btn-outline">Manage Categories <?= icon('chevron-right', 14) ?></a>
        </div>
    </div>

    <div class="card mb-16">
        <div class="card-title">General Settings</div>
        <div class="card-subtitle">Business logo, store information and preferences</div>
        <form method="post" action="<?= url('/settings/general') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Business Logo</label>
                <div class="flex items-center gap-16">
                    <?php $hasLogo = \Sukli\Services\UploadService::exists($store['logo_path'] ?? null); ?>
                    <?php if ($hasLogo): ?>
                        <img src="<?= e(\Sukli\Services\UploadService::url($store['logo_path'])) ?>" alt="" class="product-thumb product-thumb-lg">
                    <?php else: ?>
                        <div class="product-thumb product-thumb-lg product-thumb-placeholder">NO LOGO</div>
                    <?php endif; ?>
                    <div style="flex:1;">
                        <input class="form-control" type="file" name="logo" accept="image/jpeg,image/png,image/webp">
                        <div class="form-hint">JPG, PNG or WEBP — max 5MB.</div>
                    </div>
                </div>
            </div>
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
            <button type="submit" class="btn btn-primary">Save General Settings</button>
        </form>
    </div>

    <div class="card mb-16">
        <div class="card-title">Receipt Customization</div>
        <div class="card-subtitle">What prints on every POS receipt — edit and watch the preview update</div>
        <div class="grid grid-2" style="align-items:start;gap:20px;">
            <form method="post" action="<?= url('/settings/receipt') ?>" id="receipt-form">
                <?= csrf_field() ?>
                <div class="form-group"><label>Receipt Header</label><input class="form-control" id="rc-header" name="receipt_header" value="<?= e($receiptHeader) ?>" placeholder="Defaults to your Store Name"></div>
                <div class="form-group"><label>Receipt Footer</label><input class="form-control" id="rc-footer" name="receipt_footer" value="<?= e($store['receipt_footer'] ?? '') ?>"></div>
                <label class="flex items-center gap-8 mb-8" style="font-weight:500;">
                    <input type="checkbox" id="rc-show-logo" name="receipt_show_logo" value="1" <?= $receiptShowLogo ? 'checked' : '' ?> style="width:18px;height:18px;">
                    Show Logo on Receipt
                </label>
                <label class="flex items-center gap-8 mb-8" style="font-weight:500;">
                    <input type="checkbox" id="rc-show-address" name="receipt_show_address" value="1" <?= $receiptShowAddress ? 'checked' : '' ?> style="width:18px;height:18px;">
                    Show Address on Receipt
                </label>
                <label class="flex items-center gap-8 mb-8" style="font-weight:500;">
                    <input type="checkbox" id="rc-show-phone" name="receipt_show_phone" value="1" <?= $receiptShowPhone ? 'checked' : '' ?> style="width:18px;height:18px;">
                    Show Phone on Receipt
                </label>
                <label class="flex items-center gap-8 mb-16" style="font-weight:500;">
                    <input type="checkbox" name="auto_print_receipt" value="1" <?= $autoPrintReceipt ? 'checked' : '' ?> style="width:18px;height:18px;">
                    Auto Print Receipt after checkout
                </label>
                <button type="submit" class="btn btn-primary btn-block">Save Receipt Settings</button>
            </form>

            <div>
                <div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;margin-bottom:8px;">Live Preview</div>
                <div class="card" style="max-width:280px;background:#fafafa;">
                    <div style="text-align:center;margin-bottom:12px;">
                        <img src="<?= $hasLogo ? e(\Sukli\Services\UploadService::url($store['logo_path'])) : '' ?>" alt=""
                             id="rc-preview-logo" data-has-logo="<?= $hasLogo ? '1' : '0' ?>"
                             style="width:40px;height:40px;object-fit:cover;border-radius:8px;margin-bottom:6px;<?= ($receiptShowLogo && $hasLogo) ? '' : 'display:none;' ?>">
                        <div id="rc-preview-header" style="font-weight:700;font-size:14px;"><?= e($receiptHeader ?: $store['name']) ?></div>
                        <div id="rc-preview-address" style="font-size:11px;color:var(--text-muted);<?= $receiptShowAddress ? '' : 'display:none;' ?>"><?= e($store['address'] ?? '') ?></div>
                        <div id="rc-preview-phone" style="font-size:11px;color:var(--text-muted);<?= $receiptShowPhone ? '' : 'display:none;' ?>"><?= e($store['phone'] ?? '') ?></div>
                    </div>
                    <div style="border-top:1px dashed var(--border);border-bottom:1px dashed var(--border);padding:8px 0;margin-bottom:8px;font-size:11px;">
                        <div class="flex justify-between"><span>1x Sample Item</span><span><?= money(50) ?></span></div>
                    </div>
                    <div style="font-size:11px;font-weight:700;display:flex;justify-content:space-between;margin-bottom:10px;">
                        <span>Total</span><span><?= money(50) ?></span>
                    </div>
                    <div id="rc-preview-footer" style="text-align:center;font-size:11px;color:var(--text-muted);"><?= e($store['receipt_footer'] ?? '') ?></div>
                </div>
            </div>
        </div>
        <script>
        (function () {
            var header = document.getElementById('rc-header');
            var footer = document.getElementById('rc-footer');
            var showLogo = document.getElementById('rc-show-logo');
            var showAddress = document.getElementById('rc-show-address');
            var showPhone = document.getElementById('rc-show-phone');
            var pHeader = document.getElementById('rc-preview-header');
            var pFooter = document.getElementById('rc-preview-footer');
            var pLogo = document.getElementById('rc-preview-logo');
            var pAddress = document.getElementById('rc-preview-address');
            var pPhone = document.getElementById('rc-preview-phone');
            var storeName = <?= json_encode($store['name']) ?>;

            header.addEventListener('input', function () { pHeader.textContent = header.value || storeName; });
            footer.addEventListener('input', function () { pFooter.textContent = footer.value; });
            showLogo.addEventListener('change', function () { pLogo.style.display = (showLogo.checked && pLogo.getAttribute('data-has-logo') === '1') ? '' : 'none'; });
            showAddress.addEventListener('change', function () { pAddress.style.display = showAddress.checked ? '' : 'none'; });
            showPhone.addEventListener('change', function () { pPhone.style.display = showPhone.checked ? '' : 'none'; });
        })();
        </script>
    </div>

    <div class="card mb-16">
        <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:10px;">
            <div>
                <div class="card-title" style="margin:0;">Product Categories</div>
                <div class="text-muted" style="font-size:12.5px;">Add, rename, or remove the categories used across Inventory.</div>
                <div class="flex gap-8 mt-8" style="flex-wrap:wrap;">
                    <?php foreach ($categories as $c): ?><span class="badge badge-blue"><?= e($c['name']) ?></span><?php endforeach; ?>
                    <?php if (!$categories): ?><span class="text-muted">No categories yet.</span><?php endif; ?>
                </div>
            </div>
            <a href="<?= url('/inventory/categories') ?>" class="btn btn-outline">Manage Categories <?= icon('chevron-right', 14) ?></a>
        </div>
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
