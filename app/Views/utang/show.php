<?php
/** @var array $customer */
/** @var float $balance */
/** @var float $totalCredit */
/** @var float $totalPaid */
/** @var array $history */
?>
<a href="<?= url('/utang') ?>" class="text-muted" style="font-size:12.5px;">&larr; Back to Utang</a>
<h2 style="margin:6px 0 2px;"><?= e($customer['name']) ?></h2>
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
        <form method="post" action="<?= url('/utang/' . $customer['id'] . '/payment') ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Amount</label>
                <input class="form-control" type="number" step="0.01" min="0.01" max="<?= $balance > 0 ? $balance : '' ?>" name="amount" required>
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select class="form-control" name="payment_method">
                    <option value="cash">Cash</option>
                    <option value="gcash">GCash</option>
                </select>
            </div>
            <div class="form-group">
                <label>Note</label>
                <input class="form-control" type="text" name="note" placeholder="Optional">
            </div>
            <button type="submit" class="btn btn-primary btn-block" <?= $balance <= 0 ? 'disabled' : '' ?>>Record Payment</button>
        </form>
    </div>
</div>
