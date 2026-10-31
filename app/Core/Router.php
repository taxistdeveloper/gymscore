<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][] = $this->compile($path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][] = $this->compile($path, $handler);
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = $this->normalize($path);

        foreach ($this->routes[$method] ?? [] as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($route['keys'] as $key) {
                $params[$key] = $matches[$key] ?? null;
            }

            [$class, $action] = $route['handler'];
            $controller = new $class();
            $controller->$action(...array_values($params));
            return;
        }

        http_response_code(404);
        echo '404 — страница не найдена: ' . htmlspecialchars($path);
    }

    private function compile(string $path, array $handler): array
    {
        $path = $this->normalize($path);
        $keys = [];
        $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($m) use (&$keys) {
            $keys[] = $m[1];
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $path);
        $pattern = '#^' . $pattern . '$#';

        return [
            'pattern' => $pattern,
            'keys'    => $keys,
            'handler' => $handler,
        ];
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
