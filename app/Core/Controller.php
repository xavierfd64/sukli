<?php

declare(strict_types=1);

namespace Sukli\Core;

abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        View::render($view, $data, $layout);
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function back(string $fallback = '/'): void
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? null;
        $this->redirect($ref ?: $fallback);
    }

    /** Abort with a 403 if the current user's role isn't in $roles. */
    protected function authorize(array $roles): void
    {
        if (!Auth::hasRole($roles)) {
            http_response_code(403);
            View::render('errors/403', [], 'layouts/blank');
            exit;
        }
    }
}
