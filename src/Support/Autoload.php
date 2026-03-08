<?php

declare(strict_types=1);
namespace Nbkvm\Support;
class Autoload
{
    public static function register(): void
    {
        spl_autoload_register(function (string $class): void {
            $prefix = 'Nbkvm\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $path = base_path('src/' . str_replace('\\', '/', $relative) . '.php');
            if (is_file($path)) {
                require_once $path;
            }
        });
    }
}
