<?php

declare(strict_types=1);
namespace Nbkvm\Controllers;
use Nbkvm\Repositories\SnapshotRepository;
use Nbkvm\Repositories\VmRepository;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;
class VmDetailController extends BaseController
{
    public function show(Request $request): void
    {
        $id = (int) $request->input('id');
        $vm = (new VmRepository())->find($id);
        if (!$vm) {
            $this->back('虚拟机不存在。', 'error', '/');
        }
        $snapshots = array_values(array_filter((new SnapshotRepository())->all(), fn ($row) => (int) $row['vm_id'] === $id));
        $this->view('vm-detail', [
            'vm' => $vm,
            'snapshots' => $snapshots,
            'novncBaseUrl' => (string) config('novnc.base_url'),
        ]);
    }
}
