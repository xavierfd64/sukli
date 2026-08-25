<?php
/** @var array $customer */
/** @var float $balance */
/** @var float $totalCredit */
/** @var float $totalPaid */
/** @var array $history */
/** @var array $payMethods */
?>
<a href="<?= url('/utang') ?>" class="text-muted" style="font-size:12.5px;">&larr; Back to Utang</a>
<h2 style="margin:6px 0 2px;"><?= e(trim($customer['first_name'] . ' ' . ($customer['last_name'] ?? ''))) ?></h2>
<p class="text-muted"><?= e($customer['contact_number'] ?? 'No contact number on file') ?></p>

<div class="grid grid-3 mb-16">
    <div class="card"><div class="text-muted" style="font-size:11.5px;font-weight:700;text-transform:uppercase;">Outstanding Balance</div><div style="font-size:22px;font-weight:700;color:var(--orange);"><?= money($balance) ?></div></div>
    <div class="card"><div class="text-muted" style="font-size:11.5px;font-weight:700;text-transform:uppercase;">Total Credit (All Time)</div><div style="font-size:22px;font-weight:700;"><?= money($totalCredit) ?></div></div>
    <div class="card"><div class="text-muted" style="font-size:11.5px;font-weight:700;text-transform:uppercase;">Total Payments Received</div><div style="font-size:22px;font-weight:700;color:var(--green-dark);"><?= money($totalPaid) ?></div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-title">Transaction History</div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Note</th></tr></thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td class="text-muted"><?= date('M d, Y h:i A', strtotime($h['created_at'])) ?></td>
                        <td><?= $h['kind'] === 'charge' ? '<span class="badge badge-amber">Charge</span>' : '<span class="badge badge-green">Payment</span>' ?></td>
                        <td><?= $h['kind'] === 'charge' ? '+' : '-' ?><?= money($h['amount']) ?></td>
                        <td class="text-muted"><?= e($h['note'] ?? ($h['sale_id'] ? 'POS Sale #' . $h['sale_id'] : '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$history): ?><tr><td colspan="4" class="text-muted">No transactions yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Record Payment</div>
        <div class="card-subtitle">Reduces the customer's outstanding balance.</div>
        <button type="button" class="btn btn-primary btn-block" data-modal-target="#record-payment" <?= $balance <= 0 ? 'disabled' : '' ?>>Record Payment</button>
    </div>
</div>

<div class="modal-backdrop" id="record-payment">
    <div class="modal">
        <h3>Record Utang Payment</h3>
        <p class="text-muted">Outstanding balance: <strong><?= money($balance) ?></strong></p>
        <form method="post" action="<?= url('/utang/' . $customer['id'] . '/payment') ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Amount</label>
                <input class="form-control" type="number" step="0.01" min="0.01" max="<?= $balance > 0 ? $balance : '' ?>" name="amount" required autofocus>
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select class="form-control" name="payment_method">
                    <?php foreach ($payMethods as $key => $m): ?>
                        <option value="<?= e($key) ?>"><?= e($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Note</label>
                <input class="form-control" type="text" name="note" placeholder="Optional">
            </div>
            <div class="flex gap-8">
                <button type="button" class="btn btn-outline btn-block" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary btn-block">Record Payment</button>
            </div>
        </form>
    </div>
</div>
