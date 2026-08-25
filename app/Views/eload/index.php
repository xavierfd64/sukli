<?php
/** @var array $transactions */
/** @var array $totals */
/** @var string $from */
/** @var string $to */
/** @var array $customers */
/** @var array $networks */
/** @var array $eloadProducts */
/** @var array $paymentMethods */
?>
<div class="flex items-center justify-between mb-16" style="flex-wrap:wrap;gap:10px;">
    <div>
        <h2 style="margin:0;">E-Load</h2>
        <p class="text-muted" style="margin:0;">Select a customer, network and product — the price is loaded automatically.</p>
    </div>
</div>

<div class="card mb-16">
    <form method="get" action="<?= url('/eload') ?>" class="flex gap-12" style="flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="margin:0;"><label>From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="form-group" style="margin:0;"><label>To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <button type="submit" class="btn btn-outline">Filter</button>
    </form>
</div>

<div class="grid grid-2 mb-16">
    <div class="card"><div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Total E-Load Sales</div><div style="font-size:20px;font-weight:700;"><?= money($totals['selling_price']) ?></div></div>
    <div class="card"><div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Total Earnings</div><div style="font-size:20px;font-weight:700;color:var(--green-dark);"><?= money($totals['earnings']) ?></div></div>
</div>

<div class="card mb-16">
    <div class="card-title">New E-Load Sale</div>

    <div class="form-group">
        <label>Step 1 &mdash; Customer</label>
        <input type="text" id="eload-customer-search" class="form-control" placeholder="Search customer, or leave blank for Walk-In" autocomplete="off">
        <div id="eload-customer-results" class="pos-customer-results"></div>
        <div id="eload-customer-selected"></div>
    </div>

    <div class="form-group">
        <label>Step 2 &mdash; Network</label>
        <select class="form-control" id="eload-network-select">
            <option value="">Select network...</option>
            <?php foreach ($networks as $n): ?>
                <option value="<?= e($n) ?>"><?= e($n) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!$networks): ?><div class="form-hint">No networks enabled — add one in Settings &rarr; E-Load Networks.</div><?php endif; ?>
    </div>

    <div class="form-group" id="eload-product-step" style="display:none;">
        <label>Step 3 &mdash; Product</label>
        <div class="pos-grid" id="eload-product-grid"></div>
        <p class="text-muted" id="eload-no-products" style="display:none;">No active products for this network yet — add one in Settings &rarr; E-Load Products.</p>
    </div>

    <div class="card" id="eload-selected-summary" style="display:none;background:var(--bg-soft,#f4f5fa);">
        <div class="flex items-center justify-between" style="font-size:13px;"><span class="text-muted">Product</span><strong id="eload-selected-name"></strong></div>
        <div class="flex items-center justify-between" style="font-size:18px;font-weight:700;margin-top:6px;"><span>Amount to Pay</span><span id="eload-selected-price"><?= money(0) ?></span></div>
    </div>

    <button type="button" class="btn btn-primary btn-lg mt-16" id="eload-open-payment" disabled data-modal-target="#eload-payment-modal">Proceed to Payment</button>

    <form method="post" action="<?= url('/eload/checkout') ?>" id="eload-form">
        <?= csrf_field() ?>
        <input type="hidden" name="eload_product_id" id="eload-product-id-hidden">
        <input type="hidden" name="customer_id" id="eload-customer-hidden">
        <input type="hidden" name="customer_name" id="eload-customer-name-hidden">
        <input type="hidden" name="contact_number" id="eload-contact-hidden">
        <input type="hidden" name="payments_json" id="eload-payments-json">
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Date</th><th>Customer</th><th>Network</th><th>Product</th><th>Load Value</th><th>Selling Price</th><th>Earnings</th><th>Payment</th><th>Change</th></tr></thead>
            <tbody>
            <?php foreach ($transactions as $t): ?>
                <tr>
                    <td class="text-muted"><?= date('M d, h:i A', strtotime($t['created_at'])) ?></td>
                    <td><?= e($t['customer_name'] ?? 'Walk-In') ?> <?php if ($t['contact_number']): ?><div class="text-muted" style="font-size:11px;"><?= e($t['contact_number']) ?></div><?php endif; ?></td>
                    <td><?= e($t['network']) ?></td>
                    <td><?= e($t['product_name']) ?><div class="text-muted" style="font-size:11px;">Load: <?= money($t['load_value']) ?></div></td>
                    <td><?= money($t['load_value']) ?></td>
                    <td><?= money($t['selling_price']) ?></td>
                    <td style="color:var(--green-dark);font-weight:600;"><?= money($t['earnings']) ?></td>
                    <td class="text-muted"><?= e(ucfirst($t['payment_method'])) ?></td>
                    <td><?= money($t['change_amount'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?><tr><td colspan="9" class="text-muted">No E-Load transactions for this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop" id="eload-payment-modal">
    <div class="modal modal-lg">
        <h3>Payment</h3>
        <div class="flex items-center justify-between mb-16" style="font-size:18px;font-weight:700;">
            <span>Amount Due</span><span id="epm-total"><?= money(0) ?></span>
        </div>

        <?php if (count($paymentMethods) > 1): ?>
        <label class="flex items-center gap-8 mb-16" style="font-size:13px;">
            <input type="checkbox" id="epm-split-toggle"> Split Payment (pay with more than one method)
        </label>
        <?php endif; ?>

        <div id="epm-single">
            <div class="section-tabs" id="epm-method-tabs">
                <?php foreach ($paymentMethods as $key => $m): ?>
                    <a href="#" data-method="<?= e($key) ?>"><?= e($m['name']) ?></a>
                <?php endforeach; ?>
                <?php if (!$paymentMethods): ?><span class="text-muted">No payment methods are enabled. Enable one in Settings.</span><?php endif; ?>
            </div>

            <div class="form-group mt-16" id="epm-cash-fields" style="display:none;">
                <label>Customer Paid</label>
                <input type="number" step="0.01" min="0" id="epm-tendered" class="form-control">
                <div class="form-hint">Change: <strong id="epm-change"><?= money(0) ?></strong></div>
            </div>
            <p class="text-muted" id="epm-utang-hint" style="display:none;font-size:12.5px;">Utang requires a customer — select one in Step 1 above.</p>
        </div>

        <div id="epm-split" style="display:none;">
            <div id="epm-split-rows"></div>
            <button type="button" class="btn btn-outline btn-sm" id="epm-add-row"><?= icon('plus', 14) ?> Add Payment Method</button>
            <div class="flex items-center justify-between mt-16" style="font-size:13px;">
                <span>Allocated</span><strong id="epm-split-allocated"><?= money(0) ?></strong>
            </div>
            <div class="flex items-center justify-between" style="font-size:13px;">
                <span>Remaining</span><strong id="epm-split-remaining"><?= money(0) ?></strong>
            </div>
        </div>

        <div class="flex gap-8 mt-16">
            <button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button>
            <button type="button" class="btn btn-primary btn-block" id="epm-confirm" disabled>Complete Sale</button>
        </div>
    </div>
</div>

<script src="<?= asset('js/customer-picker.js') ?>"></script>
<script src="<?= asset('js/payment-shared.js') ?>"></script>
<script>
window.SUKLI_CURRENCY = "₱";
window.SUKLI_PAYMENT_METHODS = <?= json_encode(array_map(static fn ($k, $m) => ['key' => $k, 'name' => $m['name']], array_keys($paymentMethods), $paymentMethods)) ?>;
window.SUKLI_CUSTOMERS = <?= json_encode($customers) ?>;
window.SUKLI_ELOAD_PRODUCTS = <?= json_encode($eloadProducts) ?>;
</script>
<script src="<?= asset('js/eload.js') ?>"></script>
