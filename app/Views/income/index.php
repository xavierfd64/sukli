<?php
/** @var array $sources */
/** @var float $totalIncome */
/** @var string $from */
/** @var string $to */
?>
<div class="flex items-center justify-between mb-16" style="flex-wrap:wrap;gap:10px;">
    <div>
        <h2 style="margin:0;">Income Summary</h2>
        <p class="text-muted" style="margin:0;">Auto-aggregated from POS, E-Load and GCash — nothing to enter manually.</p>
    </div>
</div>

<div class="card mb-16">
    <form method="get" action="<?= url('/income') ?>" class="flex gap-12" style="flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="margin:0;"><label>From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="form-group" style="margin:0;"><label>To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <button type="submit" class="btn btn-outline">Filter</button>
        <div style="margin-left:auto;text-align:right;">
            <div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Total Income for period</div>
            <div style="font-size:22px;font-weight:700;color:var(--blue);"><?= money($totalIncome) ?></div>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Source</th><th>Transactions</th><th>Amount</th></tr></thead>
            <tbody>
            <?php foreach ($sources as $s): ?>
                <tr>
                    <td>
                        <strong><?= e($s['label']) ?></strong>
                        <div class="text-muted" style="font-size:11.5px;"><?= e($s['note']) ?></div>
                    </td>
                    <td class="text-muted"><?= $s['count'] ?></td>
                    <td style="font-weight:600;"><?= money($s['total']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td style="font-weight:700;">Total Income</td>
                    <td></td>
                    <td style="font-weight:700;color:var(--blue);"><?= money($totalIncome) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<p class="text-muted mt-16" style="font-size:12.5px;">
    Record new income by making a POS sale, an E-Load transaction, or a GCash Cash-In/Cash-Out —
    each one flows into this summary automatically.
</p>
