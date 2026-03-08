<?php

declare(strict_types=1);

namespace Nbkvm\Controllers;

use Nbkvm\Repositories\SnapshotRepository;
use Nbkvm\Repositories\TemplateRepository;
use Nbkvm\Repositories\VmRepository;
use Nbkvm\Services\NicConfigService;
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

        $template = (new TemplateRepository())->find((int) ($vm['template_id'] ?? 0));
        $vm['normalized_nics'] = (new NicConfigService())->hydrateVmNics($vm, $template);
        $snapshots = array_values(array_filter((new SnapshotRepository())->all(), fn ($row) => (int) $row['vm_id'] === $id));

        $this->view('vm-detail', [
            'vm' => $vm,
            'template' => $template,
            'snapshots' => $snapshots,
            'novncBaseUrl' => (string) config('novnc.base_url'),
        ]);
    }
}
