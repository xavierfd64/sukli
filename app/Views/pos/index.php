<?php
/** @var array $products */
/** @var array $categories */
/** @var array $customers */
/** @var array $paymentMethods */
/** @var bool $autoPrintReceipt */
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
                <input type="text" id="pos-barcode" class="form-control" placeholder="Scan barcode (auto-detected) or press Enter" autofocus>
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

            <button type="button" class="btn btn-primary btn-block btn-lg mt-16" id="pos-open-payment" disabled data-modal-target="#payment-modal">Proceed to Payment</button>

            <form method="post" action="<?= url('/pos/checkout') ?>" id="pos-form">
                <?= csrf_field() ?>
                <input type="hidden" name="cart_json" id="pos-cart-json">
                <input type="hidden" name="payments_json" id="pos-payments-json">
                <input type="hidden" name="discount_percent" id="pos-discount-hidden">
                <input type="hidden" name="customer_id" id="pos-customer-hidden">
            </form>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="payment-modal">
    <div class="modal modal-lg">
        <h3>Payment</h3>
        <div class="flex items-center justify-between mb-16" style="font-size:18px;font-weight:700;">
            <span>Total Due</span><span id="pm-total"><?= money(0) ?></span>
        </div>

        <?php if (count($paymentMethods) > 1): ?>
        <label class="flex items-center gap-8 mb-16" style="font-size:13px;">
            <input type="checkbox" id="pm-split-toggle"> Split Payment (pay with more than one method)
        </label>
        <?php endif; ?>

        <div id="pm-single">
            <div class="section-tabs" id="pm-method-tabs">
                <?php foreach ($paymentMethods as $key => $m): ?>
                    <a href="#" data-method="<?= e($key) ?>"><?= e($m['name']) ?></a>
                <?php endforeach; ?>
                <?php if (!$paymentMethods): ?><span class="text-muted">No payment methods are enabled. Enable one in Settings.</span><?php endif; ?>
            </div>

            <div class="form-group mt-16" id="pm-cash-fields" style="display:none;">
                <label>Cash Received</label>
                <input type="number" step="0.01" min="0" id="pm-tendered" class="form-control">
                <div class="form-hint">Change: <strong id="pm-change"><?= money(0) ?></strong></div>
            </div>
        </div>

        <div id="pm-split" style="display:none;">
            <div id="pm-split-rows"></div>
            <button type="button" class="btn btn-outline btn-sm" id="pm-add-row"><?= icon('plus', 14) ?> Add Payment Method</button>
            <div class="flex items-center justify-between mt-16" style="font-size:13px;">
                <span>Allocated</span><strong id="pm-split-allocated"><?= money(0) ?></strong>
            </div>
            <div class="flex items-center justify-between" style="font-size:13px;">
                <span>Remaining</span><strong id="pm-split-remaining"><?= money(0) ?></strong>
            </div>
        </div>

        <div class="form-group mt-16" id="pm-customer-fields" style="display:none;">
            <label>Customer (required for Utang)</label>
            <input type="text" id="pm-customer-search" class="form-control" placeholder="Search customer by name or contact number" autocomplete="off">
            <div id="pm-customer-results" class="pos-customer-results"></div>
            <div id="pm-customer-selected"></div>
        </div>

        <div class="flex gap-8 mt-16">
            <button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button>
            <button type="button" class="btn btn-primary btn-block" id="pm-confirm" disabled>Complete Sale</button>
        </div>
    </div>
</div>

<script>
window.SUKLI_CURRENCY = "₱";
window.SUKLI_PAYMENT_METHODS = <?= json_encode(array_map(static fn ($k, $m) => ['key' => $k, 'name' => $m['name']], array_keys($paymentMethods), $paymentMethods)) ?>;
window.SUKLI_CUSTOMERS = <?= json_encode($customers) ?>;
</script>
<script src="<?= asset('js/pos.js') ?>"></script>
