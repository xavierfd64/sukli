<?php

declare(strict_types=1);

namespace Sukli\Middleware;

use Sukli\Core\Auth;
use Sukli\Core\Request;
use Sukli\Core\View;
use Sukli\Services\FeatureService;

class FeatureMiddleware
{
    public static function require(string $key): callable
    {
        return function (Request $request) use ($key): bool {
            $storeId = Auth::storeId();
            if ($storeId === null || !FeatureService::isEnabled($storeId, $key)) {
                http_response_code(404);
                View::render('errors/404', [], 'layouts/blank');
                exit;
            }
            return true;
        };
    }
}
