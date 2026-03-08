<?php

declare(strict_types=1);
namespace Nbkvm\Controllers;
use Nbkvm\Services\AuthService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;
class AuthController extends BaseController
{
    public function loginForm(Request $request): void
    {
        $this->view('login');
    }
    public function login(Request $request): never
    {
        if (!verify_csrf((string) $request->input('_csrf'))) {
            $this->back('CSRF 校验失败。', 'error', '/login');
        }
        $ok = (new AuthService())->attempt((string) $request->input('username'), (string) $request->input('password'));
        if (!$ok) {
            $this->back('用户名或密码错误。', 'error', '/login');
        }
        $this->back('登录成功。', 'success', '/');
    }
    public function logout(Request $request): never
    {
        if (!verify_csrf((string) $request->input('_csrf'))) {
            $this->back('CSRF 校验失败。', 'error', '/');
        }
        auth_logout();
        $this->back('已退出登录。', 'success', '/login');
    }
}
