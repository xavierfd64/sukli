<?php
/** @var int $trialDays */
/** @var string $platformName */
/** @var string $themeColor */
/** @var string $themeFont */
/** @var array $fontChoices */
?>
<h2 style="margin-top:0;">Platform Settings</h2>
<p class="text-muted">Global settings for the whole platform — not tied to any single organization.</p>

<div class="card mb-16" style="max-width:480px;">
    <form method="post" action="<?= url('/platform-admin/settings') ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Platform Name</label>
            <input class="form-control" name="platform_name" value="<?= e($platformName) ?>" required>
            <div class="form-hint">Shown in the browser tab, on the login/registration pages, and in platform emails.</div>
        </div>
        <div class="form-group">
            <label>Free Trial Length (days)</label>
            <input class="form-control" type="number" min="1" name="trial_days" value="<?= (int) $trialDays ?>" required>
            <div class="form-hint">Applied to every new organization at registration or fresh install.</div>
        </div>
        <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
</div>

<div class="card" style="max-width:480px;">
    <div class="card-title">System Appearance</div>
    <p class="text-muted" style="font-size:12.5px;margin-top:-4px;">Applies to the whole platform — buttons, active navigation, and selected states. Tenants cannot change this; their own business logo/name stays separate under Settings.</p>

    <form method="post" action="<?= url('/platform-admin/settings/appearance') ?>" id="appearance-form">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Platform Color</label>
            <div class="flex items-center gap-8">
                <input type="color" id="theme_color" name="theme_color" value="<?= e($themeColor) ?>" style="width:48px;height:36px;padding:2px;border:1px solid var(--border);border-radius:6px;cursor:pointer;">
                <input class="form-control" id="theme_color_text" value="<?= e($themeColor) ?>" style="max-width:120px;" maxlength="7">
            </div>
        </div>
        <div class="form-group">
            <label>Font</label>
            <select class="form-control" id="theme_font" name="theme_font">
                <?php foreach ($fontChoices as $key => $font): ?>
                    <option value="<?= e($key) ?>" data-stack="<?= e($font['stack']) ?>" <?= $key === $themeFont ? 'selected' : '' ?>><?= e($font['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Preview</label>
            <div id="theme-preview" style="border:1px solid var(--border);border-radius:var(--radius);padding:16px;background:var(--bg);">
                <h3 id="preview-heading" style="margin:0 0 8px;">Sample Heading</h3>
                <button type="button" id="preview-button" class="btn btn-primary" style="margin-bottom:8px;">Sample Button</button>
                <p id="preview-text" class="text-muted" style="margin:0;">Sample text as it will appear throughout the platform.</p>
            </div>
        </div>

        <div class="flex gap-8">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <button type="submit" formaction="<?= url('/platform-admin/settings/appearance/reset') ?>" class="btn btn-outline" onclick="return confirm('Reset appearance to the default color and font?');">Reset to Default</button>
        </div>
    </form>
</div>

<script>
(function () {
    var colorPicker = document.getElementById('theme_color');
    var colorText = document.getElementById('theme_color_text');
    var fontSelect = document.getElementById('theme_font');
    var previewButton = document.getElementById('preview-button');
    var previewHeading = document.getElementById('preview-heading');
    var previewText = document.getElementById('preview-text');

    function applyPreview() {
        var color = colorPicker.value;
        previewButton.style.background = color;
        previewButton.style.borderColor = color;
        var stack = fontSelect.options[fontSelect.selectedIndex].getAttribute('data-stack');
        previewHeading.style.fontFamily = stack;
        previewButton.style.fontFamily = stack;
        previewText.style.fontFamily = stack;
    }

    colorPicker.addEventListener('input', function () {
        colorText.value = colorPicker.value;
        applyPreview();
    });
    colorText.addEventListener('input', function () {
        if (/^#[0-9a-fA-F]{6}$/.test(colorText.value)) {
            colorPicker.value = colorText.value;
            applyPreview();
        }
    });
    fontSelect.addEventListener('change', applyPreview);

    applyPreview();
})();
</script>
