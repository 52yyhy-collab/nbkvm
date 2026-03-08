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
function ensure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (PHP_SAPI === 'cli') {
        @session_start();
        return;
    }
    if (!headers_sent()) {
        session_start();
    }
}
function flash(?string $key = null, mixed $value = null): mixed
{
    ensure_session();
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
    ensure_session();
    $_SESSION['_old'] = $input;
}
function clear_old(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['_old']);
    }
}
function csrf_token(): string
{
    ensure_session();
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}
function verify_csrf(?string $token): bool
{
    ensure_session();
    return is_string($token) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}
function auth_user(): ?array
{
    ensure_session();
    return $_SESSION['auth_user'] ?? null;
}
function auth_check(): bool
{
    return auth_user() !== null;
}
function auth_login(array $user): void
{
    ensure_session();
    $_SESSION['auth_user'] = [
        'id' => $user['id'] ?? null,
        'username' => $user['username'] ?? 'unknown',
        'role' => $user['role'] ?? 'admin',
    ];
}
function auth_logout(): void
{
    ensure_session();
    unset($_SESSION['auth_user']);
}
function auth_role(): string
{
    $user = auth_user();
    return (string) ($user['role'] ?? 'guest');
}
function auth_is_admin(): bool
{
    return auth_role() === 'admin';
}
function auth_can_write(): bool
{
    return in_array(auth_role(), ['admin', 'operator'], true);
}
