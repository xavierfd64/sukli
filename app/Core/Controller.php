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

    /**
     * Redirects back to the page the request came from, falling back to
     * $fallback when there's no usable Referer. HTTP_REFERER is already a
     * full absolute URL (e.g. "http://host/expenses") — it must be sent
     * as-is, NOT passed through redirect()/url(), which would prepend the
     * app's base URL a second time and produce a broken path like
     * "http://host/http://host/expenses" (404). Only trusted when its host
     * matches the current request, to avoid an open redirect via a forged
     * Referer header.
     */
    protected function back(string $fallback = '/'): void
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? null;
        $currentHost = $_SERVER['HTTP_HOST'] ?? null;

        if ($ref && $currentHost && (parse_url($ref, PHP_URL_HOST) === $currentHost)) {
            header('Location: ' . $ref);
            exit;
        }

        $this->redirect($fallback);
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
