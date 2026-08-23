<?php

declare(strict_types=1);

namespace Sukli\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, array|callable $handler, array $middleware): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'middleware');
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';
            if (preg_match($pattern, $path, $matches)) {
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $request->params[$key] = $value;
                    }
                }

                foreach ($route['middleware'] as $middleware) {
                    $result = $middleware($request);
                    if ($result === false) {
                        return; // Middleware already sent a response.
                    }
                }

                $this->invoke($route['handler'], $request);
                return;
            }
        }

        http_response_code(404);
        View::render('errors/404', [], 'layouts/blank');
    }

    private function invoke(array|callable $handler, Request $request): void
    {
        if (is_callable($handler) && !is_array($handler)) {
            $handler($request);
            return;
        }

        [$controllerClass, $action] = $handler;
        $controller = new $controllerClass();
        $controller->$action($request);
    }
}
