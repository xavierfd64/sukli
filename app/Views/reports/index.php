<?php
/** @var array $reports */
/** @var string $report */
/** @var string $reportLabel */
/** @var string $from */
/** @var string $to */
/** @var array $data */
$needsDateRange = !in_array($report, ['low_stock', 'inventory_value', 'utang_balances', 'customers', 'suppliers'], true);
?>
<div class="flex items-center justify-between mb-8" style="flex-wrap:wrap;gap:10px;">
    <div>
        <h2 style="margin:0;">Reports</h2>
        <p class="text-muted" style="margin:0;">Practical reports for day-to-day store decisions</p>
    </div>
    <a href="<?= url('/reports/export.csv?' . http_build_query(['report' => $report, 'from' => $from, 'to' => $to])) ?>" class="btn btn-outline"><?= icon('reports', 16) ?> Download CSV</a>
</div>

<div class="section-tabs" style="flex-wrap:wrap;margin-top:12px;">
    <?php foreach ($reports as $key => $label): ?>
        <a href="<?= url('/reports?report=' . $key . '&from=' . $from . '&to=' . $to) ?>" class="<?= $key === $report ? 'is-active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($needsDateRange): ?>
<div class="card mb-16">
    <form method="get" action="<?= url('/reports') ?>" class="flex gap-12" style="flex-wrap:wrap;align-items:flex-end;">
        <input type="hidden" name="report" value="<?= e($report) ?>">
        <div class="form-group" style="margin:0;"><label>From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="form-group" style="margin:0;"><label>To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <button type="submit" class="btn btn-outline">Update</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
<?php if ($report === 'sales'): ?>
    <div class="card-title">Sales by Day</div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Transactions</th><th>Total Sales</th></tr></thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?><tr><td><?= date('M d, Y', strtotime($r['day'])) ?></td><td><?= (int) $r['transactions'] ?></td><td><?= money($r['total']) ?></td></tr><?php endforeach; ?>
        <?php if (!$data['rows']): ?><tr><td colspan="3" class="text-muted">No sales in this period.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="2" style="font-weight:700;">Total</td><td style="font-weight:700;"><?= money($data['total']) ?></td></tr></tfoot>
    </table></div>

<?php elseif ($report === 'income'): ?>
    <div class="card-title">Income by Category</div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Category</th><th>Count</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?><tr><td><?= e($r['category']) ?></td><td><?= (int) $r['cnt'] ?></td><td><?= money($r['total']) ?></td></tr><?php endforeach; ?>
        <?php if (!$data['rows']): ?><tr><td colspan="3" class="text-muted">No income records in this period.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="2" style="font-weight:700;">Total</td><td style="font-weight:700;"><?= money($data['total']) ?></td></tr></tfoot>
    </table></div>

<?php elseif ($report === 'expense'): ?>
    <div class="card-title">Expenses by Category</div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Category</th><th>Count</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?><tr><td><?= e($r['category']) ?></td><td><?= (int) $r['cnt'] ?></td><td><?= money($r['total']) ?></td></tr><?php endforeach; ?>
        <?php if (!$data['rows']): ?><tr><td colspan="3" class="text-muted">No expense records in this period.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="2" style="font-weight:700;">Total</td><td style="font-weight:700;"><?= money($data['total']) ?></td></tr></tfoot>
    </table></div>

<?php elseif ($report === 'net'): ?>
    <div class="card-title">Net Income Summary</div>
    <div class="grid grid-2">
        <div>
            <div class="flex justify-between" style="padding:8px 0;border-bottom:1px solid var(--border);"><span>POS Sales</span><strong><?= money($data['sales']) ?></strong></div>
            <div class="flex justify-between" style="padding:8px 0;border-bottom:1px solid var(--border);"><span>Other Income</span><strong><?= money($data['other_income']) ?></strong></div>
            <div class="flex justify-between" style="padding:8px 0;border-bottom:1px solid var(--border);"><span>Total Income</span><strong><?= money($data['total_income']) ?></strong></div>
            <div class="flex justify-between" style="padding:8px 0;border-bottom:1px solid var(--border);"><span>Expenses</span><strong style="color:var(--red);">-<?= money($data['expenses']) ?></strong></div>
            <div class="flex justify-between" style="padding:12px 0;font-size:18px;"><span>Net Income</span><strong style="color:var(--green-dark);"><?= money($data['net']) ?></strong></div>
        </div>
    </div>

<?php elseif ($report === 'low_stock'): ?>
    <div class="card-title">Low Stock Products</div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Product</th><th>Current Stock</th><th>Min Stock</th></tr></thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= (int) $r['current_stock'] ?> <?= e($r['unit']) ?></td><td><?= (int) $r['min_stock'] ?></td></tr><?php endforeach; ?>
        <?php if (!$data['rows']): ?><tr><td colspan="3" class="text-muted">No low stock products.</td></tr><?php endif; ?>
        </tbody>
    </table></div>

<?php elseif ($report === 'inventory_value'): ?>
    <div class="card-title">Inventory Value</div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Product</th><th>Supplier</th><th>Stock</th><th>Cost Value</th><th>Retail Value</th></tr></thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?><tr><td><?= e($r['name']) ?></td><td class="text-muted"><?= e($r['supplier']) ?></td><td><?= (int) $r['current_stock'] ?></td><td><?= money($r['cost_value']) ?></td><td><?= money($r['retail_value']) ?></td></tr><?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="3" style="font-weight:700;">Total</td><td style="font-weight:700;"><?= money($data['total_cost_value']) ?></td><td style="font-weight:700;"><?= money($data['total_retail_value']) ?></td></tr></tfoot>
    </table></div>

<?php elseif ($report === 'eload'): ?>
    <div class="card-title">E-Load Records</div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Customer</th><th>Network</th><th>Load</th><th>Profit</th></tr></thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?><tr><td><?= date('M d, Y', strtotime($r['transacted_at'])) ?></td><td><?= e($r['customer_name'] ?? '—') ?></td><td><?= e($r['network'] ?? '—') ?></td><td><?= money($r['load_amount']) ?></td><td><?= money($r['profit']) ?></td></tr><?php endforeach; ?>
        <?php if (!$data['rows']): ?><tr><td colspan="5" class="text-muted">No E-Load records in this period.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="3" style="font-weight:700;">Total</td><td style="font-weight:700;"><?= money($data['total_load']) ?></td><td style="font-weight:700;"><?= money($data['total_profit']) ?></td></tr></tfoot>
    </table></div>

<?php elseif ($report === 'gcash'): ?>
    <div class="card-title">GCash Records</div>
    <div class="flex gap-16 mb-16">
        <div>Cash-In: <strong style="color:var(--green-dark);"><?= money($data['cash_in']) ?></strong></div>
        <div>Cash-Out: <strong style="color:var(--red);"><?= money($data['cash_out']) ?></strong></div>
    </div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Reference</th></tr></thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?><tr><td><?= date('M d, Y', strtotime($r['transacted_at'])) ?></td><td><?= $r['type'] === 'cash_in' ? 'Cash-In' : 'Cash-Out' ?></td><td><?= money($r['amount']) ?></td><td><?= e($r['customer_reference'] ?? '—') ?></td></tr><?php endforeach; ?>
        <?php if (!$data['rows']): ?><tr><td colspan="4" class="text-muted">No GCash records in this period.</td></tr><?php endif; ?>
        </tbody>
    </table></div>

<?php elseif ($report === 'utang_balances'): ?>
    <div class="card-title">Utang Balances</div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Customer</th><th>Outstanding Balance</th></tr></thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= money($r['outstanding_balance']) ?></td></tr><?php endforeach; ?>
        <?php if (!$data['rows']): ?><tr><td colspan="2" class="text-muted">No outstanding balances.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td style="font-weight:700;">Total</td><td style="font-weight:700;"><?= money($data['total']) ?></td></tr></tfoot>
    </table></div>

<?php elseif ($report === 'utang_payments'): ?>
    <div class="card-title">Utang Payments</div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Customer</th><th>Amount</th><th>Method</th></tr></thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?><tr><td><?= date('M d, Y', strtotime($r['created_at'])) ?></td><td><?= e($r['customer_name']) ?></td><td><?= money($r['amount']) ?></td><td><?= e(ucfirst($r['payment_method'])) ?></td></tr><?php endforeach; ?>
        <?php if (!$data['rows']): ?><tr><td colspan="4" class="text-muted">No payments in this period.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="2" style="font-weight:700;">Total</td><td style="font-weight:700;"><?= money($data['total']) ?></td></tr></tfoot>
    </table></div>

<?php elseif ($report === 'customers'): ?>
    <div class="card-title">Customers</div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Name</th><th>Contact Number</th><th>Status</th><th>Outstanding Balance</th></tr></thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?><tr><td><?= e($r['name']) ?></td><td class="text-muted"><?= e($r['contact_number'] ?? '—') ?></td><td><span class="badge <?= $r['status'] === 'active' ? 'badge-green' : 'badge-gray' ?>"><?= e(ucfirst($r['status'])) ?></span></td><td><?= money($r['outstanding_balance']) ?></td></tr><?php endforeach; ?>
        <?php if (!$data['rows']): ?><tr><td colspan="4" class="text-muted">No customers yet.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="3" style="font-weight:700;">Total Customers</td><td style="font-weight:700;"><?= $data['total'] ?></td></tr></tfoot>
    </table></div>

<?php elseif ($report === 'suppliers'): ?>
    <div class="card-title">Suppliers</div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Supplier</th><th>Contact Person</th><th>Contact Number</th><th>Address</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?><tr><td><?= e($r['display_name']) ?></td><td class="text-muted"><?= e($r['contact_person'] ?: '—') ?></td><td class="text-muted"><?= e($r['contact_number'] ?? '—') ?></td><td class="text-muted"><?= e($r['address'] ?? '—') ?></td><td><span class="badge <?= $r['status'] === 'active' ? 'badge-green' : 'badge-gray' ?>"><?= e(ucfirst($r['status'])) ?></span></td></tr><?php endforeach; ?>
        <?php if (!$data['rows']): ?><tr><td colspan="5" class="text-muted">No suppliers yet.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="4" style="font-weight:700;">Total Suppliers</td><td style="font-weight:700;"><?= $data['total'] ?></td></tr></tfoot>
    </table></div>
<?php endif; ?>
</div>
