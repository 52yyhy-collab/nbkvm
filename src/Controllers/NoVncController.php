<?php

declare(strict_types=1);
namespace Nbkvm\Controllers;
use Nbkvm\Services\AuditService;
use Nbkvm\Services\NoVncService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;
class NoVncController extends BaseController
{
    public function start(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        try {
            (new NoVncService())->start((string) $request->input('vm_name'), (int) ($request->input('port') ?: 6080));
            (new AuditService())->log('启动 noVNC 代理', 'vm', (string) $request->input('vm_name'));
            $this->back('noVNC 代理已启动。');
        } catch (\Throwable $e) {
            $this->back('noVNC 代理启动失败：' . $e->getMessage(), 'error');
        }
    }
    public function stop(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        try {
            (new NoVncService())->stop((string) $request->input('vm_name'));
            (new AuditService())->log('停止 noVNC 代理', 'vm', (string) $request->input('vm_name'));
            $this->back('noVNC 代理已停止。');
        } catch (\Throwable $e) {
            $this->back('noVNC 代理停止失败：' . $e->getMessage(), 'error');
        }
    }
}
