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
        $redirectTo = (string) $request->input('redirect_to', '/');

        try {
            (new NetworkService())->saveWithPool($request->all());
            (new AuditService())->log('保存网络配置', 'network', (string) $request->input('name'));
            $this->back('网络配置保存成功。', 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('网络配置保存失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }

    public function delete(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        $redirectTo = (string) $request->input('redirect_to', '/');

        try {
            $id = (int) $request->input('id');
            if ($id > 0) {
                (new NetworkService())->delete($id);
            } else {
                (new NetworkService())->delete((string) $request->input('name'));
            }
            (new AuditService())->log('删除网络', 'network', (string) ($id > 0 ? $id : $request->input('name')));
            $this->back('网络删除成功。', 'success', $redirectTo);
        } catch (\Throwable $e) {
            $this->back('网络删除失败：' . $e->getMessage(), 'error', $redirectTo);
        }
    }
}
