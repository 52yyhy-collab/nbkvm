<?php

declare(strict_types=1);
namespace Nbkvm\Controllers;
use Nbkvm\Services\SnapshotService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;
class SnapshotController extends BaseController
{
    public function store(Request $request): never
    {
        try {
            (new SnapshotService())->create((int) $request->input('vm_id'), (string) $request->input('name'));
            $this->back('快照创建成功。');
        } catch (\Throwable $e) {
            $this->back('快照创建失败：' . $e->getMessage(), 'error');
        }
    }
}
