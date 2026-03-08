<?php

declare(strict_types=1);
namespace Nbkvm\Support;
class Request
{
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }
    public function host(): string
    {
        return (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1');
    }
    public function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        return rtrim($path, '/') ?: '/';
    }
    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }
    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }
    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }
}
