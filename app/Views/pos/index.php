<?php
/** @var array $products */
/** @var array $categories */
/** @var array $customers */
/** @var array $features */
$gcashOn = !empty($features['gcash']['is_enabled']);
$utangOn = !empty($features['utang']['is_enabled']);
?>
<div class="pos-layout">
    <div class="pos-products">
        <div class="card mb-16">
            <div class="input-icon-group">
                <?= icon('search', 16) ?>
                <input type="text" id="pos-search" class="form-control" placeholder="Search product by name">
            </div>
            <div class="input-icon-group mt-16">
                <?= icon('barcode', 16) ?>
                <input type="text" id="pos-barcode" class="form-control" placeholder="Scan or type barcode, then press Enter" autofocus>
            </div>
        </div>

        <div class="section-tabs" id="pos-category-tabs">
            <a href="#" class="is-active" data-category="all">All Items</a>
            <?php foreach ($categories as $cat): ?>
                <a href="#" data-category="<?= e($cat) ?>"><?= e($cat) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="pos-grid" id="pos-grid">
            <?php foreach ($products as $p): ?>
                <button type="button" class="pos-product" data-category="<?= e($p['category_name'] ?: 'Others') ?>" data-name="<?= e(strtolower($p['name'])) ?>"
                        data-id="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>" data-pname="<?= e($p['name']) ?>" data-stock="<?= (int) $p['current_stock'] ?>"
                        data-barcode="<?= e($p['barcode'] ?? '') ?>">
                    <div class="pos-product-name"><?= e($p['name']) ?></div>
                    <div class="pos-product-price"><?= money($p['selling_price']) ?></div>
                    <div class="pos-product-stock">Stock: <?= (int) $p['current_stock'] ?></div>
                </button>
            <?php endforeach; ?>
            <?php if (!$products): ?><p class="text-muted">No products available. Add stock in Inventory first.</p><?php endif; ?>
        </div>
    </div>

    <div class="pos-cart" id="pos-cart">
        <div class="card">
            <div class="flex items-center justify-between mb-16">
                <div class="card-title" style="margin:0;">Current Sale</div>
                <a href="#" id="pos-clear-cart" class="text-muted" style="font-size:12px;color:var(--red);">Clear Cart</a>
            </div>
            <div id="pos-cart-items">
                <p class="text-muted" id="pos-cart-empty">Cart is empty. Tap a product to add it.</p>
            </div>

            <div class="mt-16" style="border-top:1px solid var(--border);padding-top:12px;">
                <div class="flex items-center justify-between" style="font-size:13px;margin-bottom:6px;">
                    <span>Subtotal</span><strong id="pos-subtotal"><?= money(0) ?></strong>
                </div>
                <div class="flex items-center justify-between" style="font-size:13px;margin-bottom:6px;">
                    <span>Discount %</span>
                    <input type="number" id="pos-discount" min="0" max="100" value="0" class="form-control" style="width:80px;text-align:right;">
                </div>
                <div class="flex items-center justify-between" style="font-size:16px;font-weight:700;margin-top:8px;">
                    <span>Total</span><span id="pos-total"><?= money(0) ?></span>
                </div>
            </div>

            <?php if ($utangOn && $customers): ?>
            <div class="form-group mt-16" id="pos-customer-group" style="display:none;">
                <label>Customer (required for Utang)</label>
                <select id="pos-customer" class="form-control">
                    <option value="">Select customer...</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="form-group mt-16" id="pos-cash-group" style="display:none;">
                <label>Cash Received</label>
                <input type="number" step="0.01" min="0" id="pos-tendered" class="form-control">
                <div class="form-hint">Change: <strong id="pos-change"><?= money(0) ?></strong></div>
            </div>

            <div class="grid grid-3 gap-8 mt-16">
                <button type="button" class="btn pay-cash pay-btn" data-method="cash">Cash</button>
                <?php if ($gcashOn): ?><button type="button" class="btn pay-gcash pay-btn" data-method="gcash">GCash</button><?php endif; ?>
                <?php if ($utangOn): ?><button type="button" class="btn pay-utang pay-btn" data-method="utang">Utang</button><?php endif; ?>
            </div>

            <form method="post" action="<?= url('/pos/checkout') ?>" id="pos-form">
                <?= csrf_field() ?>
                <input type="hidden" name="cart_json" id="pos-cart-json">
                <input type="hidden" name="payment_method" id="pos-payment-method">
                <input type="hidden" name="discount_percent" id="pos-discount-hidden">
                <input type="hidden" name="amount_tendered" id="pos-tendered-hidden">
                <input type="hidden" name="customer_id" id="pos-customer-hidden">
                <button type="submit" class="btn btn-primary btn-block btn-lg mt-16" id="pos-submit" disabled>Pay &amp; Complete Sale</button>
            </form>
        </div>
    </div>
</div>

<script>window.SUKLI_CURRENCY = "₱";</script>
<script src="<?= asset('js/pos.js') ?>"></script>
