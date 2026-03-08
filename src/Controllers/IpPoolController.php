<?php

declare(strict_types=1);

namespace Nbkvm\Controllers;

use Nbkvm\Services\AuditService;
use Nbkvm\Services\IpPoolService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;

class IpPoolController extends BaseController
{
    public function store(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        try {
            $id = (int) $request->input('id');
            if ($id > 0) {
                (new IpPoolService())->update($id, $request->all());
                (new AuditService())->log('更新 IP 池', 'ip_pool', (string) $id);
                $this->back('IP 池更新成功。');
            }
            (new IpPoolService())->create($request->all());
            (new AuditService())->log('创建 IP 池', 'ip_pool', (string) $request->input('name'));
            $this->back('IP 池创建成功。');
        } catch (\Throwable $e) {
            $this->back('IP 池保存失败：' . $e->getMessage(), 'error');
        }
    }

    public function delete(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        $this->requireWrite();
        try {
            (new IpPoolService())->delete((int) $request->input('id'));
            (new AuditService())->log('删除 IP 池', 'ip_pool', (string) $request->input('id'));
            $this->back('IP 池删除成功。');
        } catch (\Throwable $e) {
            $this->back('IP 池删除失败：' . $e->getMessage(), 'error');
        }
    }
}
