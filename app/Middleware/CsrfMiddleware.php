<?php

declare(strict_types=1);

namespace Sukli\Middleware;

use Sukli\Core\Csrf;
use Sukli\Core\Request;
use Sukli\Core\View;

class CsrfMiddleware
{
    public static function handle(): callable
    {
        return function (Request $request): bool {
            if ($request->isPost() && !Csrf::verify($request->input('_csrf'))) {
                http_response_code(419);
                View::render('errors/419', [], 'layouts/blank');
                exit;
            }
            return true;
        };
    }
}
