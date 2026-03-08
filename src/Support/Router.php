<?php

declare(strict_types=1);
namespace Nbkvm\Support;
use Closure;
class Router
{
    private array $routes = [];
    public function get(string $path, Closure|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }
    public function post(string $path, Closure|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }
    private function add(string $method, string $path, Closure|array $handler): void
    {
        $this->routes[$method][rtrim($path, '/') ?: '/'] = $handler;
    }
    public function dispatch(Request $request): mixed
    {
        $method = $request->method();
        $path = $request->path();
        $handler = $this->routes[$method][$path] ?? null;
        if ($handler === null) {
            http_response_code(404);
            echo '404 Not Found';
            return null;
        }
        if ($handler instanceof Closure) {
            return $handler($request);
        }
        [$class, $action] = $handler;
        $controller = new $class();
        return $controller->$action($request);
    }
}
