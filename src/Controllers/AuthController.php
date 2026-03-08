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
    public function changePassword(Request $request): never
    {
        if (!verify_csrf((string) $request->input('_csrf'))) {
            $this->back('CSRF 校验失败。', 'error', '/');
        }
        $user = auth_user();
        if (!$user || empty($user['id'])) {
            $this->back('未登录。', 'error', '/login');
        }
        $password = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirm');
        if (strlen($password) < 8) {
            $this->back('新密码至少需要 8 位。', 'error', '/');
        }
        if ($password !== $confirm) {
            $this->back('两次输入的新密码不一致。', 'error', '/');
        }
        (new \Nbkvm\Repositories\UserRepository())->updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        $this->back('密码修改成功。');
    }
}
