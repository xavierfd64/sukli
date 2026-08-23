<?php /** @var array $store */ ?>
<h2>Store Setup</h2>
<p class="install-lede">Basic store information — you can change this anytime later in Settings.</p>

<form method="post" action="<?= url('/install/store') ?>">
    <?= csrf_field() ?>
    <div class="form-group">
        <label>Store Name</label>
        <input class="form-control" type="text" name="store_name" value="<?= e($store['name'] ?? '') ?>" placeholder="Sukli Sari-Sari Store" required autofocus>
    </div>
    <div class="form-group">
        <label>Store Address</label>
        <input class="form-control" type="text" name="store_address" value="<?= e($store['address'] ?? '') ?>" placeholder="Barangay, City">
    </div>
    <div class="form-group">
        <label>Contact Number</label>
        <input class="form-control" type="text" name="contact_number" value="<?= e($store['phone'] ?? '') ?>" placeholder="0917-000-0000">
    </div>

    <div class="flex gap-8 mt-16">
        <a href="<?= url('/install/admin') ?>" class="btn btn-outline btn-block">Back</a>
        <button type="submit" class="btn btn-primary btn-block">Continue</button>
    </div>
</form>
