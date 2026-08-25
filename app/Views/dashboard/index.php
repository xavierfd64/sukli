<?php
/** @var float $salesToday */
/** @var float $salesDelta */
/** @var float $incomeToday */
/** @var float $incomeDelta */
/** @var float $expenseToday */
/** @var float $expenseDelta */
/** @var float $netToday */
/** @var float $netDelta */
/** @var array $paymentSummary */
/** @var float $cashOnHand */
/** @var float $gcashBalance */
/** @var array $lowStock */
/** @var array $recentTransactions */
/** @var array $topProducts */
/** @var array $features */
/** @var string $period */
/** @var string $from */
/** @var string $to */
/** @var string $periodLabel */

if (!function_exists('delta_badge')) {
    function delta_badge(float $delta): string
    {
        $sign = $delta >= 0 ? '+' : '';
        return $sign . number_format($delta, 1) . '% vs previous period';
    }
}

$paymentColors = ['cash' => '#22c55e', 'gcash' => '#3b82f6', 'utang' => '#f97316'];
$paymentTotal = array_sum(array_column($paymentSummary, 'total')) ?: 1;
$gradientStops = [];
$cursor = 0;
foreach ($paymentSummary as $row) {
    $pct = ((float) $row['total'] / $paymentTotal) * 100;
    $color = $paymentColors[$row['payment_method']] ?? '#9ca3af';
    $gradientStops[] = "{$color} {$cursor}% " . ($cursor + $pct) . '%';
    $cursor += $pct;
}
$gradientCss = $gradientStops ? implode(', ', $gradientStops) : '#e5e7eb 0% 100%';
?>
<div class="card mb-16">
    <form method="get" action="<?= url('/dashboard') ?>" class="flex items-center gap-12" style="flex-wrap:wrap;">
        <div class="section-tabs" style="margin:0;border:none;">
            <?php foreach (['today' => 'Today', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Annually'] as $key => $label): ?>
                <a href="<?= url('/dashboard?period=' . $key) ?>" class="<?= $period === $key ? 'is-active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="flex items-center gap-8" style="margin-left:auto;flex-wrap:wrap;">
            <input type="hidden" name="period" value="custom">
            <input class="form-control" type="date" name="from" value="<?= e($from) ?>" style="width:auto;">
            <span class="text-muted">to</span>
            <input class="form-control" type="date" name="to" value="<?= e($to) ?>" style="width:auto;">
            <button type="submit" class="btn btn-outline btn-sm">Custom Range</button>
        </div>
    </form>
</div>

<div class="grid grid-4 mb-16">
    <div class="kpi-card kpi-green">
        <div class="kpi-icon"><?= icon('pos', 30) ?></div>
        <div class="kpi-label">Total Sales (<?= e($periodLabel) ?>)</div>
        <div class="kpi-value"><?= money($salesToday) ?></div>
        <div class="kpi-delta"><?= delta_badge($salesDelta) ?></div>
    </div>
    <div class="kpi-card kpi-blue">
        <div class="kpi-icon"><?= icon('wallet', 30) ?></div>
        <div class="kpi-label">Total Income (<?= e($periodLabel) ?>)</div>
        <div class="kpi-value"><?= money($incomeToday) ?></div>
        <div class="kpi-delta"><?= delta_badge($incomeDelta) ?></div>
    </div>
    <div class="kpi-card kpi-orange">
        <div class="kpi-icon"><?= icon('expense', 30) ?></div>
        <div class="kpi-label">Total Expenses (<?= e($periodLabel) ?>)</div>
        <div class="kpi-value"><?= money($expenseToday) ?></div>
        <div class="kpi-delta"><?= delta_badge($expenseDelta) ?></div>
    </div>
    <div class="kpi-card kpi-purple">
        <div class="kpi-icon"><?= icon('reports', 30) ?></div>
        <div class="kpi-label">Net Income (<?= e($periodLabel) ?>)</div>
        <div class="kpi-value"><?= money($netToday) ?></div>
        <div class="kpi-delta"><?= delta_badge($netDelta) ?></div>
    </div>
</div>

<div class="grid grid-3 mb-16">
    <div class="card">
        <div class="card-title">Payment Summary</div>
        <div class="card-subtitle">Completed sales by method for the selected period</div>
        <div class="flex items-center gap-16">
            <div style="width:110px;height:110px;border-radius:50%;background:conic-gradient(<?= $gradientCss ?>);flex-shrink:0;"></div>
            <div style="flex:1;">
                <?php foreach ($paymentSummary as $row): ?>
                    <div class="flex items-center justify-between" style="margin-bottom:6px;font-size:12.5px;">
                        <span class="flex items-center gap-8">
                            <span style="width:10px;height:10px;border-radius:50%;background:<?= $paymentColors[$row['payment_method']] ?? '#9ca3af' ?>;display:inline-block;"></span>
                            <?= e(ucfirst($row['payment_method'])) ?>
                        </span>
                        <strong><?= money($row['total']) ?></strong>
                    </div>
                <?php endforeach; ?>
                <?php if (!$paymentSummary): ?><p class="text-muted">No sales yet for this period.</p><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Low Stock Alert</div>
        <div class="card-subtitle">Products at or below their minimum stock</div>
        <?php foreach ($lowStock as $p): ?>
            <div class="flex items-center justify-between" style="padding:7px 0;border-bottom:1px solid var(--border);">
                <span><?= e($p['name']) ?></span>
                <span class="badge badge-amber"><?= (int) $p['current_stock'] ?> left</span>
            </div>
        <?php endforeach; ?>
        <?php if (!$lowStock): ?><p class="text-muted">All stock levels look healthy.</p><?php endif; ?>
        <a href="<?= url('/inventory?filter=low_stock') ?>" class="btn btn-outline btn-sm mt-16">View All Products</a>
    </div>

    <div class="card">
        <div class="card-title">Cash &amp; GCash Balance</div>
        <div class="card-subtitle">Running balances</div>
        <div style="background:#f0fdf4;border-radius:10px;padding:12px 14px;margin-bottom:10px;">
            <div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Cash on Hand</div>
            <div style="font-size:20px;font-weight:700;color:var(--green-dark);"><?= money($cashOnHand) ?></div>
        </div>
        <?php if (!empty($features['gcash']['is_enabled']) && !empty($features['gcash']['show_in_dashboard'])): ?>
        <div style="background:#eff6ff;border-radius:10px;padding:12px 14px;">
            <div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">GCash Balance</div>
            <div style="font-size:20px;font-weight:700;color:var(--blue);"><?= money($gcashBalance) ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="flex items-center justify-between mb-16">
            <div class="card-title" style="margin:0;">Recent Transactions</div>
            <a href="<?= url('/reports') ?>" class="text-muted" style="font-size:12px;">View Full Report</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Type</th><th>Description</th><th>Amount</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($recentTransactions as $t): ?>
                    <tr>
                        <td><span class="badge badge-gray"><?= e($t['type']) ?></span></td>
                        <td><?= e($t['description']) ?></td>
                        <td><?= money($t['amount']) ?></td>
                        <td class="text-muted"><?= date('h:i A', strtotime($t['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentTransactions): ?><tr><td colspan="4" class="text-muted">No transactions yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Top Selling Products</div>
        <div class="card-subtitle">For the selected period, by quantity sold</div>
        <?php $i = 1; foreach ($topProducts as $p): ?>
            <div class="flex items-center justify-between" style="padding:8px 0;border-bottom:1px solid var(--border);">
                <span><?= $i++ ?>. <?= e($p['product_name']) ?></span>
                <strong><?= (int) $p['qty'] ?> sold</strong>
            </div>
        <?php endforeach; ?>
        <?php if (!$topProducts): ?><p class="text-muted">No sales recorded yet.</p><?php endif; ?>
    </div>
</div>
