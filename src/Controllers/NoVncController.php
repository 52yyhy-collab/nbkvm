<?php

declare(strict_types=1);

namespace Nbkvm\Controllers;

use Nbkvm\Repositories\VmRepository;
use Nbkvm\Services\AuditService;
use Nbkvm\Services\NoVncService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;

class NoVncController extends BaseController
{
    public function open(Request $request): never
    {
        $vm = (new VmRepository())->find((int) $request->input('id'));
        if (!$vm) {
            $this->back('虚拟机不存在。', 'error', '/?page=vms');
        }
        $this->requireWrite();
        $port = 6080 + (int) $vm['id'];
        $service = new NoVncService();
        if (!$service->isRunning((string) $vm['name'])) {
            $service->start((string) $vm['name'], $port);
        }
        (new AuditService())->log('打开 noVNC', 'vm', (string) $vm['name']);
        $host = preg_replace('/:\d+$/', '', $request->host());
        redirect('http://' . $host . ':' . $port . '/vnc.html?autoconnect=true');
    }

    public function start(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');

        try {
            (new NoVncService())->start((string) $request->input('vm_name'), (int) ($request->input('port') ?: 6080));
            (new AuditService())->log('启动 noVNC 代理', 'vm', (string) $request->input('vm_name'));
            $this->back('noVNC 代理已启动。', 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('noVNC 代理启动失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }

    public function stop(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');

        try {
            (new NoVncService())->stop((string) $request->input('vm_name'));
            (new AuditService())->log('停止 noVNC 代理', 'vm', (string) $request->input('vm_name'));
            $this->back('noVNC 代理已停止。', 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('noVNC 代理停止失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }
}
