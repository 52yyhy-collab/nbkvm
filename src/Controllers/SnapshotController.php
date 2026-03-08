<?php

declare(strict_types=1);
namespace Nbkvm\Controllers;
use Nbkvm\Services\AuditService;
use Nbkvm\Services\SnapshotService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;
class SnapshotController extends BaseController
{
    public function store(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        try {
            (new SnapshotService())->create((int) $request->input('vm_id'), (string) $request->input('name'));
            (new AuditService())->log('创建快照', 'snapshot', (string) $request->input('name'));
            $this->back('快照创建成功。');
        } catch (\Throwable $e) {
            $this->back('快照创建失败：' . $e->getMessage(), 'error');
        }
    }
    public function revert(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        try {
            (new SnapshotService())->revert((string) $request->input('vm_name'), (string) $request->input('snapshot_name'));
            (new AuditService())->log('回滚快照', 'snapshot', (string) $request->input('snapshot_name'));
            $this->back('快照回滚成功。');
        } catch (\Throwable $e) {
            $this->back('快照回滚失败：' . $e->getMessage(), 'error');
        }
    }
    public function delete(Request $request): never
    {
        $this->requireCsrf((string) $request->input('_csrf'));
        try {
            (new SnapshotService())->delete((string) $request->input('vm_name'), (string) $request->input('snapshot_name'));
            (new AuditService())->log('删除快照', 'snapshot', (string) $request->input('snapshot_name'));
            $this->back('快照删除成功。');
        } catch (\Throwable $e) {
            $this->back('快照删除失败：' . $e->getMessage(), 'error');
        }
    }
}
