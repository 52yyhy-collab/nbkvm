<?php

declare(strict_types=1);
namespace Nbkvm\Services;
use Nbkvm\Repositories\SnapshotRepository;
use Nbkvm\Repositories\VmRepository;
use RuntimeException;
class SnapshotService
{
    public function create(int $vmId, string $name): void
    {
        $vm = (new VmRepository())->find($vmId);
        if (!$vm) {
            throw new RuntimeException('虚拟机不存在。');
        }
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('快照名称不能为空。');
        }
        $cmd = sprintf('%s -c %s snapshot-create-as --domain %s --name %s --atomic 2>&1',
            escapeshellcmd((string) config('libvirt.virsh')),
            escapeshellarg((string) config('libvirt.uri')),
            escapeshellarg((string) $vm['name']),
            escapeshellarg($name)
        );
        exec($cmd, $output, $code);
        if ($code !== 0) {
            throw new RuntimeException('创建快照失败：' . implode("\n", $output));
        }
        (new SnapshotRepository())->create([
            'vm_id' => $vmId,
            'name' => $name,
            'status' => 'created',
            'created_at' => date('c'),
        ]);
    }
}
