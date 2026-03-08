<?php

declare(strict_types=1);
namespace Nbkvm\Controllers;
use Nbkvm\Services\AuditService;
use Nbkvm\Services\NetworkService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;
class NetworkController extends BaseController
{
    public function store(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        try {
            (new NetworkService())->create($request->all());
            (new AuditService())->log('创建网络', 'network', (string) $request->input('name'));
            $this->back('网络创建成功。');
        } catch (\Throwable $e) {
            $this->back('网络创建失败：' . $e->getMessage(), 'error');
        }
    }
}
