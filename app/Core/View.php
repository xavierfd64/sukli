<?php

declare(strict_types=1);

namespace Sukli\Core;

use Sukli\Services\FeatureService;

class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $data['currentUser'] = Auth::check() ? Auth::user() : null;
        $data['currentPath'] = (new Request())->path();
        $data['features'] = (Auth::check() && Auth::storeId()) ? FeatureService::all(Auth::storeId()) : [];

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

    private static function capture(string $path, array $data): string
    {
        if (!is_file($path)) {
            throw new \RuntimeException("View not found: {$path}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return (string) ob_get_clean();
    }
}
