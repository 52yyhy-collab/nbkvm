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
    protected function requireAdmin(string $redirectTo = '/'): void
    {
        if (!auth_is_admin()) {
            $this->back('需要管理员权限。', 'error', $redirectTo);
        }
    }
    protected function requireWrite(string $redirectTo = '/'): void
    {
        if (!auth_can_write()) {
            $this->back('当前账户没有写入权限。', 'error', $redirectTo);
        }
    }
}
