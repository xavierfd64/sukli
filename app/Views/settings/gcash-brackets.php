<?php
/** @var array $brackets */
?>
<a href="<?= url('/settings') ?>" class="text-muted" style="font-size:12.5px;">&larr; Back to Settings</a>
<h2 style="margin:6px 0 2px;">GCash Charge Brackets</h2>
<p class="text-muted">The GCash form auto-suggests a service charge based on the transaction amount and these brackets.</p>

<div class="card mb-16">
    <form method="post" action="<?= url('/settings/gcash-brackets') ?>" class="flex gap-8" style="flex-wrap:wrap;align-items:flex-end;">
        <?= csrf_field() ?>
        <div class="form-group" style="margin:0;"><label>Min Amount</label><input class="form-control" type="number" step="0.01" min="0" name="min_amount" required style="width:130px;"></div>
        <div class="form-group" style="margin:0;"><label>Max Amount</label><input class="form-control" type="number" step="0.01" min="0" name="max_amount" placeholder="No limit" style="width:130px;"></div>
        <div class="form-group" style="margin:0;"><label>Charge</label><input class="form-control" type="number" step="0.01" min="0" name="charge" required style="width:110px;"></div>
        <button type="submit" class="btn btn-outline">Add Bracket</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Amount Range</th><th>Charge</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($brackets as $b): ?>
                <tr>
                    <td><?= money($b['min_amount']) ?> &ndash; <?= $b['max_amount'] !== null ? money($b['max_amount']) : 'no limit' ?></td>
                    <td><strong><?= money($b['charge']) ?></strong></td>
                    <td>
                        <form method="post" action="<?= url('/settings/gcash-brackets/' . $b['id'] . '/delete') ?>" onsubmit="return confirm('Remove this bracket?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline" style="color:var(--red);">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$brackets): ?><tr><td colspan="3" class="text-muted">No brackets yet — the service charge field will need to be entered manually.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
