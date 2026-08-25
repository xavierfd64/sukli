<?php
/** @var array $labels */
/** @var string $paperSize */
/** @var array $paperSizes */
$pageSizeCss = ['a4' => 'A4', 'letter' => 'letter', 'legal' => 'legal'][$paperSize] ?? 'A4';
?>
<div class="flex items-center justify-between mb-16 no-print">
    <div>
        <h2 style="margin:0;">Barcode Labels</h2>
        <p class="text-muted" style="margin:0;"><?= count($labels) ?> label(s) &middot; <?= e($paperSizes[$paperSize] ?? $paperSize) ?></p>
    </div>
    <div class="flex gap-8">
        <a href="<?= url('/inventory/labels') ?>" class="btn btn-outline">&larr; Change Selection</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Print / Save as PDF</button>
    </div>
</div>

<div class="label-sheet">
    <?php foreach ($labels as $p): ?>
        <div class="label-card">
            <div class="label-name"><?= e($p['name']) ?></div>
            <div class="label-price"><?= money($p['selling_price']) ?></div>
            <?php if ($p['barcode']): ?>
                <div class="label-barcode"><?= \Sukli\Services\BarcodeService::svg($p['barcode'], 2, 45) ?></div>
            <?php else: ?>
                <div class="label-barcode text-muted" style="font-size:11px;">No barcode assigned — edit this product to generate one.</div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if (!$labels): ?><p class="text-muted">No labels to print.</p><?php endif; ?>
</div>

<style>
@page { size: <?= $pageSizeCss ?>; margin: 10mm; }

.label-sheet { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.label-card { border: 1px dashed var(--border); border-radius: 8px; padding: 10px; text-align: center; background: #fff; }
.label-name { font-size: 12px; font-weight: 700; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.label-price { font-size: 13px; font-weight: 700; color: var(--green-dark); margin-bottom: 4px; }
.label-barcode svg { max-width: 100%; height: auto; }

@media print {
    .sidebar, .topbar, .bottom-nav, .no-print { display: none !important; }
    .main { margin-left: 0 !important; }
    body { background: #fff; }
    .label-sheet { grid-template-columns: repeat(3, 1fr); }
    .label-card { break-inside: avoid; border: 1px solid #000; }
}
</style>
