<?php
/** @var array $records */
/** @var float $cashIn */
/** @var float $cashOut */
/** @var float $serviceCharges */
/** @var string $from */
/** @var string $to */
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
        <form method="post" action="<?= url('/gcash') ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Type</label>
                <select class="form-control" name="type">
                    <option value="cash_in">Cash-In (customer gives cash, receives GCash)</option>
                    <option value="cash_out">Cash-Out (customer sends GCash, receives cash)</option>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Amount</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" required></div>
                <div class="form-group"><label>Service Charge</label><input class="form-control" type="number" step="0.01" min="0" name="service_charge" value="0"></div>
            </div>
            <div class="form-group"><label>Customer / Reference</label><input class="form-control" name="customer_reference"></div>
            <div class="form-group"><label>Notes</label><input class="form-control" name="notes"></div>
            <div class="flex gap-8"><button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button><button class="btn btn-primary btn-block">Save</button></div>
        </form>
    </div>
</div>
