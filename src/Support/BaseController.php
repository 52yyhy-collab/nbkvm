<?php

declare(strict_types=1);
namespace Nbkvm\Support;
abstract class BaseController
{
    protected function view(string $template, array $data = []): void
    {
        View::render($template, $data);
    }
    protected function back(string $message, string $type = 'success', string $to = '/'): never
    {
        flash($type, $message);
        redirect($to);
    }
    protected function requireCsrf(string $token, string $redirectTo = '/'): void
    {
        if (!verify_csrf($token)) {
            $this->back('CSRF 校验失败。', 'error', $redirectTo);
        }
    }
}
