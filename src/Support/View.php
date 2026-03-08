<?php

declare(strict_types=1);
namespace Nbkvm\Support;
class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $flash = flash();
        $viewPath = base_path('views/' . $template . '.php');
        $layoutPath = base_path('views/layout.php');
        ob_start();
        require $viewPath;
        $content = ob_get_clean();
        require $layoutPath;
    }
}
