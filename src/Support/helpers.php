<?php

declare(strict_types=1);
function base_path(string $path = ''): string
{
    $base = dirname(__DIR__, 2);
    return $path ? $base . '/' . ltrim($path, '/') : $base;
}
function config(string $key, mixed $default = null): mixed
{
    static $config;
    if ($config === null) {
        $config = require base_path('config/app.php');
    }
    $segments = explode('.', $key);
    $value = $config;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}
function url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}
function flash(?string $key = null, mixed $value = null): mixed
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if ($key !== null && $value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    if ($key === null) {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }
    $message = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $message;
}
function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}
function with_old(array $input): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['_old'] = $input;
}
function clear_old(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['_old']);
    }
}
