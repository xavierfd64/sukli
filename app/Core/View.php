<?php

declare(strict_types=1);

namespace Sukli\Core;

use Sukli\Services\BranchAccessService;
use Sukli\Services\FeatureService;
use Sukli\Services\PlatformSettingsService;

class View
{
    private static array $orgNameCache = [];

    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $data['currentUser'] = Auth::check() ? Auth::user() : null;
        $data['currentPath'] = (new Request())->path();
        $data['features'] = (Auth::check() && Auth::storeId()) ? FeatureService::all(Auth::storeId()) : [];

        // Single source of truth for every <title> tag (see layouts/*.php) —
        // no layout should ever hardcode the app name, so a Platform Admin
        // renaming the platform takes effect everywhere on the next request.
        // Skipped for the installer layout: those pages render before a
        // database connection is necessarily configured at all, and must
        // keep working with no DB reachable — see PlatformSettingsService's
        // own defensive try/catch for the "table doesn't exist yet" case,
        // but a bad/absent DB host still exit()s inside Database::connection(),
        // which no try/catch here could recover from anyway.
        $data['platformName'] = $data['themeColor'] = $data['themeFont'] = null;
        if ($layout !== 'layouts/install') {
            $data['platformName'] = PlatformSettingsService::get('platform_name', 'Sukli');
            $data['themeColor'] = PlatformSettingsService::get('theme_color', PlatformSettingsService::DEFAULT_ACCENT);
            $data['themeFont'] = PlatformSettingsService::get('theme_font', PlatformSettingsService::DEFAULT_FONT);
        }
        $data['tenantName'] = null;
        if (Auth::check() && Auth::organizationId()) {
            $orgId = (int) Auth::organizationId();
            if (!array_key_exists($orgId, self::$orgNameCache)) {
                $org = Database::one("SELECT name FROM organizations WHERE id = ?", [$orgId]);
                self::$orgNameCache[$orgId] = $org['name'] ?? null;
            }
            $data['tenantName'] = self::$orgNameCache[$orgId];
        }

        // Available for every view via the topbar branch switcher — computed
        // once here rather than in every controller. Skipped for Platform
        // Admins viewing their own account (they may have no organization
        // context worth switching branches within) and anyone not logged in.
        $data['branchSwitcherStores'] = [];
        $data['branchSwitcherCurrent'] = null;
        if (Auth::check() && Auth::organizationId()) {
            $stores = BranchAccessService::accessibleStores((int) Auth::id(), (int) Auth::organizationId(), Auth::hasRole(['owner']));
            if (count($stores) > 1) {
                $data['branchSwitcherStores'] = $stores;
                foreach ($stores as $s) {
                    if ((int) $s['id'] === (int) Auth::storeId()) {
                        $data['branchSwitcherCurrent'] = $s;
                        break;
                    }
                }
            }
        }

        $content = self::capture(__DIR__ . "/../Views/{$view}.php", $data);

        if ($layout === null) {
            echo $content;
            return;
        }

        $data['content'] = $content;
        echo self::capture(__DIR__ . "/../Views/{$layout}.php", $data);
    }

    public static function partial(string $view, array $data = []): string
    {
        return self::capture(__DIR__ . "/../Views/{$view}.php", $data);
    }

    /**
     * Parameters are deliberately given unusual names (not $path/$data) —
     * extract(..., EXTR_SKIP) refuses to overwrite a variable that already
     * exists in this scope, so a view data key literally named "path" or
     * "data" would otherwise silently vanish behind this method's own
     * parameters instead of reaching the included template.
     */
    private static function capture(string $__sukli_view_path, array $__sukli_view_data): string
    {
        if (!is_file($__sukli_view_path)) {
            throw new \RuntimeException("View not found: {$__sukli_view_path}");
        }
        extract($__sukli_view_data, EXTR_SKIP);
        unset($__sukli_view_data);
        ob_start();
        include $__sukli_view_path;
        return (string) ob_get_clean();
    }
}
