<?php
/** @var array $customers */
/** @var float $totalOutstanding */
/** @var int $customersWithBalance */
?>
<h2 style="margin-top:0;">Utang / Credit</h2>
<p class="text-muted">Customers with outstanding balances</p>

<div class="grid grid-2 mb-16">
    <div class="kpi-card kpi-orange">
        <div class="kpi-icon"><?= icon('utang', 30) ?></div>
        <div class="kpi-label">Total Outstanding</div>
        <div class="kpi-value"><?= money($totalOutstanding) ?></div>
    </div>
    <div class="kpi-card kpi-purple">
        <div class="kpi-icon"><?= icon('customers', 30) ?></div>
        <div class="kpi-label">Customers with Balance</div>
        <div class="kpi-value"><?= $customersWithBalance ?></div>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Customer</th><th>Contact</th><th>Outstanding Balance</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><strong><?= e(trim($c['first_name'] . ' ' . ($c['last_name'] ?? ''))) ?></strong></td>
                    <td class="text-muted"><?= e($c['contact_number'] ?? '—') ?></td>
                    <td><span class="badge badge-amber"><?= money($c['outstanding_balance']) ?></span></td>
                    <td><a href="<?= url('/utang/' . $c['id']) ?>" class="btn btn-sm btn-outline">View / Record Payment</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$customers): ?><tr><td colspan="4" class="text-muted">No outstanding balances.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
