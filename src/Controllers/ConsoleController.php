<?php

declare(strict_types=1);

namespace Nbkvm\Controllers;

use Nbkvm\Repositories\VmRepository;
use Nbkvm\Services\AuditService;
use Nbkvm\Services\SerialConsoleService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;
use Nbkvm\Support\Response;

class ConsoleController extends BaseController
{
    public function open(Request $request): void
    {
        $vm = $this->findVm((int) $request->input('id'));
        $this->requireWrite();

        $this->view('console-serial', [
            'vm' => $vm,
            'consoleSnapshot' => (new SerialConsoleService())->snapshot($vm),
        ]);
    }

    public function status(Request $request): never
    {
        $vm = $this->findVm((int) $request->input('id'));
        $this->requireWrite();
        Response::json((new SerialConsoleService())->snapshot($vm));
    }

    public function start(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $vm = $this->findVm((int) $request->input('id'));

        try {
            $result = (new SerialConsoleService())->start($vm);
            (new AuditService())->log('启动串口控制台', 'vm', (string) $vm['name']);
            Response::json($result);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }
    }

    public function send(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $vm = $this->findVm((int) $request->input('id'));

        try {
            $result = (new SerialConsoleService())->send(
                $vm,
                (string) $request->input('input', ''),
                ((string) $request->input('append_enter', '1')) === '1'
            );
            Response::json($result);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }
    }

    public function stop(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $vm = $this->findVm((int) $request->input('id'));

        try {
            $result = (new SerialConsoleService())->stop($vm);
            (new AuditService())->log('停止串口控制台', 'vm', (string) $vm['name']);
            Response::json($result);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }
    }

    private function findVm(int $id): array
    {
        $vm = (new VmRepository())->find($id);
        if (!$vm) {
            $this->back('虚拟机不存在。', 'error', '/?page=vms');
        }
        return $vm;
    }
}
