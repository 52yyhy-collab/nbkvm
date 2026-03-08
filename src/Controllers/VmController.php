<?php

declare(strict_types=1);
namespace Nbkvm\Controllers;
use Nbkvm\Services\VmService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;
class VmController extends BaseController
{
    public function store(Request $request): never
    {
        try {
            (new VmService())->createFromTemplate($request->all());
            $this->back('虚拟机创建成功。');
        } catch (\Throwable $e) {
            $this->back('虚拟机创建失败：' . $e->getMessage(), 'error');
        }
    }
    public function start(Request $request): never
    {
        try {
            (new VmService())->start((int) $request->input('id'));
            $this->back('虚拟机已启动。');
        } catch (\Throwable $e) {
            $this->back('启动失败：' . $e->getMessage(), 'error');
        }
    }
    public function shutdown(Request $request): never
    {
        try {
            (new VmService())->shutdown((int) $request->input('id'));
            $this->back('已发送关机指令。');
        } catch (\Throwable $e) {
            $this->back('关机失败：' . $e->getMessage(), 'error');
        }
    }
    public function destroy(Request $request): never
    {
        try {
            (new VmService())->destroy((int) $request->input('id'));
            $this->back('已强制关闭虚拟机。');
        } catch (\Throwable $e) {
            $this->back('强制停止失败：' . $e->getMessage(), 'error');
        }
    }
    public function delete(Request $request): never
    {
        try {
            (new VmService())->delete((int) $request->input('id'), $request->input('remove_storage') === '1');
            $this->back('虚拟机已删除。');
        } catch (\Throwable $e) {
            $this->back('删除失败：' . $e->getMessage(), 'error');
        }
    }
}
