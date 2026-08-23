<?php
/** @var array $sale */
/** @var array $items */
/** @var array $store */
?>
<div class="flex items-center justify-between mb-16">
    <h2 style="margin:0;">Receipt #<?= e($sale['sale_number']) ?></h2>
    <div class="flex gap-8">
        <button type="button" class="btn btn-outline" onclick="window.print()">Print</button>
        <a href="<?= url('/pos') ?>" class="btn btn-primary">New Sale</a>
    </div>
</div>

<div class="card" style="max-width:420px;" id="receipt-print">
    <div style="text-align:center;margin-bottom:14px;">
        <strong style="font-size:16px;"><?= e($store['name'] ?? 'Sukli Store') ?></strong>
        <div class="text-muted" style="font-size:12px;"><?= e($store['address'] ?? '') ?></div>
    </div>
    <div style="font-size:12.5px;margin-bottom:10px;">
        <div class="flex justify-between"><span>Receipt No.</span><span>#<?= e($sale['sale_number']) ?></span></div>
        <div class="flex justify-between"><span>Date</span><span><?= date('M d, Y h:i A', strtotime($sale['created_at'])) ?></span></div>
        <div class="flex justify-between"><span>Cashier</span><span><?= e($sale['cashier_name']) ?></span></div>
        <?php if ($sale['customer_name']): ?><div class="flex justify-between"><span>Customer</span><span><?= e($sale['customer_name']) ?></span></div><?php endif; ?>
    </div>
    <div style="border-top:1px dashed var(--border);border-bottom:1px dashed var(--border);padding:10px 0;margin-bottom:10px;">
        <?php foreach ($items as $it): ?>
            <div class="flex justify-between" style="font-size:12.5px;margin-bottom:4px;">
                <span><?= (int) $it['quantity'] ?>x <?= e($it['product_name']) ?></span>
                <span><?= money($it['line_total']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <div style="font-size:12.5px;">
        <div class="flex justify-between"><span>Subtotal</span><span><?= money($sale['subtotal']) ?></span></div>
        <div class="flex justify-between"><span>Discount</span><span>-<?= money($sale['discount_amount']) ?></span></div>
        <div class="flex justify-between" style="font-size:15px;font-weight:700;margin-top:6px;"><span>Total</span><span><?= money($sale['total']) ?></span></div>
        <div class="flex justify-between mt-16"><span>Payment Method</span><span><?= e(ucfirst($sale['payment_method'])) ?></span></div>
        <?php if ($sale['payment_method'] === 'cash'): ?>
            <div class="flex justify-between"><span>Cash Received</span><span><?= money($sale['amount_tendered']) ?></span></div>
            <div class="flex justify-between"><span>Change</span><span><?= money($sale['change_amount']) ?></span></div>
        <?php endif; ?>
    </div>
    <div style="text-align:center;margin-top:16px;font-size:12px;" class="text-muted">
        <?= e($store['receipt_footer'] ?? 'Salamat po!') ?>
    </div>
</div>

<style>
@media print {
    .sidebar, .topbar, .bottom-nav, .btn { display: none !important; }
    .main { margin-left: 0 !important; }
    body { background: #fff; }
}
</style>
