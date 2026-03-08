<?php

declare(strict_types=1);

namespace Nbkvm\Controllers;

use Nbkvm\Services\AuditService;
use Nbkvm\Services\TaskService;
use Nbkvm\Services\VmService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;

class VmController extends BaseController
{
    public function store(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');

        try {
            (new TaskService())->run('创建虚拟机', 'vm', (string) $request->input('name'), fn () => (new VmService())->createFromTemplate($request->all()));
            (new AuditService())->log('创建虚拟机', 'vm', (string) $request->input('name'));
            $this->back('虚拟机创建成功。', 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('虚拟机创建失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }

    public function update(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');

        try {
            (new VmService())->update((int) $request->input('id'), $request->all());
            (new AuditService())->log('更新虚拟机配置', 'vm', (string) $request->input('id'));
            $this->back('虚拟机配置更新成功。', 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('虚拟机配置更新失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }

    public function start(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');

        try {
            (new TaskService())->run('启动虚拟机', 'vm', (string) $request->input('id'), fn () => (new VmService())->start((int) $request->input('id')));
            (new AuditService())->log('启动虚拟机', 'vm', (string) $request->input('id'));
            $this->back('虚拟机已启动。', 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('启动失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }

    public function shutdown(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');

        try {
            (new TaskService())->run('关闭虚拟机', 'vm', (string) $request->input('id'), fn () => (new VmService())->shutdown((int) $request->input('id')));
            (new AuditService())->log('关闭虚拟机', 'vm', (string) $request->input('id'));
            $this->back('已发送关机指令。', 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('关机失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }

    public function destroy(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');

        try {
            (new TaskService())->run('强制停止虚拟机', 'vm', (string) $request->input('id'), fn () => (new VmService())->destroy((int) $request->input('id')));
            (new AuditService())->log('强制停止虚拟机', 'vm', (string) $request->input('id'));
            $this->back('已强制关闭虚拟机。', 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('强制停止失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }

    public function delete(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');

        try {
            (new TaskService())->run('删除虚拟机', 'vm', (string) $request->input('id'), fn () => (new VmService())->delete((int) $request->input('id'), $request->input('remove_storage') === '1'));
            (new AuditService())->log('删除虚拟机', 'vm', (string) $request->input('id'));
            $this->back('虚拟机已删除。', 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('删除失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }
}
