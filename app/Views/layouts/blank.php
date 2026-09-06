<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e((($pageTitle ?? null) ? $pageTitle . ' — ' : '') . ($platformName ?? 'Sukli')) ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>:root{--accent:<?= e($themeColor ?? '#16a34a') ?>;--font-family:<?= e(\Sukli\Services\PlatformSettingsService::FONT_CHOICES[$themeFont ?? 'system']['stack'] ?? \Sukli\Services\PlatformSettingsService::FONT_CHOICES['system']['stack']) ?>;}</style>
</head>
<body class="blank-page">
<?= $content ?>
</body>
</html>
