<?php

declare(strict_types=1);
namespace Nbkvm\Controllers;
use Nbkvm\Services\AuditService;
use Nbkvm\Services\UserService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;
class UserController extends BaseController
{
    public function store(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireAdmin();
        try {
            (new UserService())->create((string) $request->input('username'), (string) $request->input('password'), (string) $request->input('role', 'admin'));
            (new AuditService())->log('创建用户', 'user', (string) $request->input('username'));
            $this->back('用户创建成功。');
        } catch (\Throwable $e) {
            $this->back('用户创建失败：' . $e->getMessage(), 'error');
        }
    }
    public function delete(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireAdmin();
        try {
            (new UserService())->delete((int) $request->input('id'));
            (new AuditService())->log('删除用户', 'user', (string) $request->input('id'));
            $this->back('用户删除成功。');
        } catch (\Throwable $e) {
            $this->back('用户删除失败：' . $e->getMessage(), 'error');
        }
    }
}
