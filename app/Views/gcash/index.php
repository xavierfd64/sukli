<?php
/** @var array $records */
/** @var float $cashIn */
/** @var float $cashOut */
/** @var float $serviceCharges */
/** @var string $from */
/** @var string $to */
/** @var array $customers */
/** @var array $brackets */
?>
<div class="flex items-center justify-between mb-16" style="flex-wrap:wrap;gap:10px;">
    <div>
        <h2 style="margin:0;">GCash Cash-In / Cash-Out</h2>
        <p class="text-muted" style="margin:0;">Recording only — no direct GCash API integration</p>
    </div>
    <button type="button" class="btn btn-primary" data-modal-target="#add-gcash"><?= icon('plus', 16) ?> Add Transaction</button>
</div>

<div class="card mb-16">
    <form method="get" action="<?= url('/gcash') ?>" class="flex gap-12" style="flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="margin:0;"><label>From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="form-group" style="margin:0;"><label>To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <button type="submit" class="btn btn-outline">Filter</button>
    </form>
</div>

<div class="grid grid-3 mb-16">
    <div class="card"><div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Cash-In</div><div style="font-size:20px;font-weight:700;color:var(--green-dark);"><?= money($cashIn) ?></div></div>
    <div class="card"><div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Cash-Out</div><div style="font-size:20px;font-weight:700;color:var(--red);"><?= money($cashOut) ?></div></div>
    <div class="card"><div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Service Charges Earned</div><div style="font-size:20px;font-weight:700;"><?= money($serviceCharges) ?></div></div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Service Charge</th><th>Reference</th><th>Notes</th></tr></thead>
            <tbody>
            <?php foreach ($records as $r): ?>
                <tr>
                    <td class="text-muted"><?= date('M d, h:i A', strtotime($r['transacted_at'])) ?></td>
                    <td><?= $r['type'] === 'cash_in' ? '<span class="badge badge-green">Cash-In</span>' : '<span class="badge badge-red">Cash-Out</span>' ?></td>
                    <td><?= money($r['amount']) ?></td>
                    <td class="text-muted"><?= money($r['service_charge']) ?></td>
                    <td><?= e($r['customer_reference'] ?? '—') ?></td>
                    <td class="text-muted"><?= e($r['notes'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$records): ?><tr><td colspan="6" class="text-muted">No GCash transactions for this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop" id="add-gcash">
    <div class="modal">
        <h3>Add GCash Transaction</h3>
        <form method="post" action="<?= url('/gcash') ?>" id="gcash-form">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Type</label>
                <select class="form-control" name="type">
                    <option value="cash_in">Cash-In (customer gives cash, receives GCash)</option>
                    <option value="cash_out">Cash-Out (customer sends GCash, receives cash)</option>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Amount</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" id="gcash-amount" required></div>
                <div class="form-group">
                    <label>Service Charge</label>
                    <input class="form-control" type="number" step="0.01" min="0" name="service_charge" id="gcash-service-charge" value="0">
                    <div class="form-hint" id="gcash-charge-hint"></div>
                </div>
            </div>
            <div class="form-group">
                <label>Customer / Reference</label>
                <input type="text" id="gcash-customer-search" class="form-control" placeholder="Search customer, or leave blank" autocomplete="off">
                <div id="gcash-customer-results" class="pos-customer-results"></div>
                <div id="gcash-customer-selected"></div>
                <input type="hidden" name="customer_reference" id="gcash-customer-reference">
            </div>
            <div class="form-group"><label>Notes</label><input class="form-control" name="notes"></div>
            <div class="flex gap-8"><button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button><button class="btn btn-primary btn-block">Save</button></div>
        </form>
    </div>
</div>

<script src="<?= asset('js/customer-picker.js') ?>"></script>
<script>
(function () {
    var brackets = <?= json_encode($brackets) ?>;
    var amountInput = document.getElementById('gcash-amount');
    var chargeInput = document.getElementById('gcash-service-charge');
    var chargeHint = document.getElementById('gcash-charge-hint');
    var chargeTouched = false;

    function suggestedCharge(amount) {
        for (var i = 0; i < brackets.length; i++) {
            var b = brackets[i];
            var min = parseFloat(b.min_amount);
            var max = b.max_amount === null ? null : parseFloat(b.max_amount);
            if (amount >= min && (max === null || amount <= max)) return parseFloat(b.charge);
        }
        return null;
    }

    amountInput.addEventListener('input', function () {
        var amount = parseFloat(amountInput.value) || 0;
        var suggested = suggestedCharge(amount);
        if (suggested === null) {
            chargeHint.textContent = '';
            return;
        }
        chargeHint.textContent = 'Suggested charge for this amount: ₱' + suggested.toFixed(2);
        if (!chargeTouched) chargeInput.value = suggested.toFixed(2);
    });
    chargeInput.addEventListener('input', function () { chargeTouched = true; });

    var refHidden = document.getElementById('gcash-customer-reference');
    SukliCustomerPicker({
        input: document.getElementById('gcash-customer-search'),
        results: document.getElementById('gcash-customer-results'),
        selected: document.getElementById('gcash-customer-selected'),
        customers: <?= json_encode($customers) ?>,
        onSelect: function (c) { refHidden.value = c.name; },
        onClear: function () { refHidden.value = ''; },
    });
})();
</script>
