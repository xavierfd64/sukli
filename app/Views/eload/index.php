<?php
/** @var array $records */
/** @var array $totals */
/** @var string $from */
/** @var string $to */
?>
<div class="flex items-center justify-between mb-16" style="flex-wrap:wrap;gap:10px;">
    <div>
        <h2 style="margin:0;">E-Load Records</h2>
        <p class="text-muted" style="margin:0;">Recording only — no telecom API integration</p>
    </div>
    <button type="button" class="btn btn-primary" data-modal-target="#add-eload"><?= icon('plus', 16) ?> Add Transaction</button>
</div>

<div class="card mb-16">
    <form method="get" action="<?= url('/eload') ?>" class="flex gap-12" style="flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="margin:0;"><label>From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="form-group" style="margin:0;"><label>To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <button type="submit" class="btn btn-outline">Filter</button>
    </form>
</div>

<div class="grid grid-3 mb-16">
    <div class="card"><div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Total Load Sold</div><div style="font-size:20px;font-weight:700;"><?= money($totals['load_amount']) ?></div></div>
    <div class="card"><div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Total Received</div><div style="font-size:20px;font-weight:700;"><?= money($totals['amount_received']) ?></div></div>
    <div class="card"><div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Total Profit</div><div style="font-size:20px;font-weight:700;color:var(--green-dark);"><?= money($totals['profit']) ?></div></div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Date</th><th>Customer</th><th>Network</th><th>Load</th><th>Received</th><th>Profit</th><th>Notes</th></tr></thead>
            <tbody>
            <?php foreach ($records as $r): ?>
                <tr>
                    <td class="text-muted"><?= date('M d, h:i A', strtotime($r['transacted_at'])) ?></td>
                    <td><?= e($r['customer_name'] ?? '—') ?> <?php if ($r['mobile_number']): ?><div class="text-muted" style="font-size:11px;"><?= e($r['mobile_number']) ?></div><?php endif; ?></td>
                    <td><?= e($r['network'] ?? '—') ?></td>
                    <td><?= money($r['load_amount']) ?></td>
                    <td><?= money($r['amount_received']) ?></td>
                    <td style="color:var(--green-dark);font-weight:600;"><?= money($r['profit']) ?></td>
                    <td class="text-muted"><?= e($r['notes'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$records): ?><tr><td colspan="7" class="text-muted">No E-Load transactions for this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop" id="add-eload">
    <div class="modal">
        <h3>Add E-Load Transaction</h3>
        <form method="post" action="<?= url('/eload') ?>">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group"><label>Customer Name</label><input class="form-control" name="customer_name"></div>
                <div class="form-group"><label>Mobile Number</label><input class="form-control" name="mobile_number"></div>
            </div>
            <div class="form-group"><label>Network</label><input class="form-control" name="network" placeholder="Smart, Globe, TNT, DITO..."></div>
            <div class="form-row">
                <div class="form-group"><label>Load Amount</label><input class="form-control" type="number" step="0.01" min="0.01" name="load_amount" required></div>
                <div class="form-group"><label>Amount Received from Customer</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount_received" required></div>
            </div>
            <div class="form-group"><label>Cost (what you paid for the load)</label><input class="form-control" type="number" step="0.01" min="0" name="cost" value="0"></div>
            <div class="form-group"><label>Notes</label><input class="form-control" name="notes"></div>
            <div class="flex gap-8"><button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button><button class="btn btn-primary btn-block">Save</button></div>
        </form>
    </div>
</div>
