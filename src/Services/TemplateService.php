<?php

declare(strict_types=1);

namespace Nbkvm\Services;

use Nbkvm\Repositories\ImageRepository;
use Nbkvm\Repositories\TemplateRepository;
use Nbkvm\Repositories\VmRepository;
use RuntimeException;

class TemplateService
{
    public function create(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('模板名称不能为空。');
        }

        $imageId = (int) ($data['image_id'] ?? 0);
        $image = (new ImageRepository())->find($imageId);
        if (!$image) {
            throw new RuntimeException('所选镜像不存在。');
        }

        $cpuSockets = max(1, (int) ($data['cpu_sockets'] ?? 1));
        $cpuCores = max(1, (int) ($data['cpu_cores'] ?? 1));
        $cpuThreads = max(1, (int) ($data['cpu_threads'] ?? 1));
        $cpu = $cpuSockets * $cpuCores * $cpuThreads;

        $fallbackNetwork = trim((string) ($data['network_name'] ?? config('libvirt.default_network')));
        if ($fallbackNetwork === '') {
            $fallbackNetwork = (string) config('libvirt.default_network');
        }

        $memoryMb = max(256, (int) ($data['memory_mb'] ?? 2048));
        $memoryMaxMb = ($data['memory_max_mb'] ?? '') !== '' ? max($memoryMb, (int) $data['memory_max_mb']) : null;
        $diskSizeGb = max(5, (int) ($data['disk_size_gb'] ?? 20));
        $diskBus = trim((string) ($data['disk_bus'] ?? config('libvirt.default_disk_bus')));
        if ($diskBus === '') {
            $diskBus = (string) config('libvirt.default_disk_bus');
        }

        $cloudInitEnabled = ($data['cloud_init_enabled'] ?? '') === '1' ? 1 : 0;
        $disks = $this->normalizeDisks($data, $diskSizeGb, $diskBus);
        $nics = (new NicConfigService())->normalizeTemplateInput($data, $fallbackNetwork);
        if ((new NicConfigService())->requiresCloudInitConfig($nics) && $cloudInitEnabled !== 1) {
            throw new RuntimeException('模板网卡包含静态地址或 IP 池，必须启用 cloud-init。');
        }
        $primaryNetworkName = (new NicConfigService())->primaryNetworkName($nics);

        return (new TemplateRepository())->create([
            'name' => $name,
            'image_id' => $imageId,
            'image_type' => $image['extension'],
            'os_variant' => trim((string) ($data['os_variant'] ?? config('libvirt.default_os_variant'))),
            'cpu' => $cpu,
            'memory_mb' => $memoryMb,
            'disk_size_gb' => $diskSizeGb,
            'disk_bus' => $diskBus,
            'network_name' => $primaryNetworkName,
            'notes' => trim((string) ($data['notes'] ?? '')),
            'cloud_init_enabled' => $cloudInitEnabled,
            'cloud_init_user' => trim((string) ($data['cloud_init_user'] ?? 'ubuntu')),
            'cloud_init_password' => trim((string) ($data['cloud_init_password'] ?? '')) ?: null,
            'cloud_init_ssh_key' => trim((string) ($data['cloud_init_ssh_key'] ?? '')) ?: null,
            'virtualization_mode' => trim((string) ($data['virtualization_mode'] ?? config('virtualization.default_mode'))),
            'cpu_sockets' => $cpuSockets,
            'cpu_cores' => $cpuCores,
            'cpu_threads' => $cpuThreads,
            'machine_type' => trim((string) ($data['machine_type'] ?? config('virtualization.default_machine'))),
            'firmware_type' => trim((string) ($data['firmware_type'] ?? config('virtualization.default_firmware'))),
            'gpu_type' => trim((string) ($data['gpu_type'] ?? config('virtualization.default_gpu'))),
            'autostart_default' => ($data['autostart_default'] ?? '') === '1' ? 1 : 0,
            'memory_max_mb' => $memoryMaxMb,
            'memory_overcommit_percent' => max(100, (int) ($data['memory_overcommit_percent'] ?? 100)),
            'disk_overcommit_enabled' => ($data['disk_overcommit_enabled'] ?? '') === '1' ? 1 : 0,
            'disks_json' => json_encode($disks, JSON_UNESCAPED_UNICODE),
            'nics_json' => json_encode($nics, JSON_UNESCAPED_UNICODE),
            'created_at' => date('c'),
        ]);
    }

    public function delete(int $id): void
    {
        foreach ((new VmRepository())->all() as $vm) {
            if ((int) $vm['template_id'] === $id) {
                throw new RuntimeException('该模板仍有虚拟机关联，不能删除。');
            }
        }
        (new TemplateRepository())->delete($id);
    }

    private function normalizeDisks(array $data, int $defaultSizeGb, string $defaultBus): array
    {
        $disks = json_decode((string) ($data['disks_json'] ?? '[]'), true);
        if (!is_array($disks)) {
            $disks = [];
        }

        $normalized = [];
        foreach ($disks as $index => $disk) {
            if (!is_array($disk)) {
                continue;
            }
            $normalized[] = [
                'name' => trim((string) ($disk['name'] ?? ('disk' . $index))) ?: ('disk' . $index),
                'size_gb' => max(1, (int) ($disk['size_gb'] ?? $defaultSizeGb)),
                'bus' => trim((string) ($disk['bus'] ?? $defaultBus)) ?: $defaultBus,
                'format' => trim((string) ($disk['format'] ?? 'qcow2')) ?: 'qcow2',
            ];
        }

        if ($normalized === []) {
            $normalized[] = [
                'name' => 'disk0',
                'size_gb' => $defaultSizeGb,
                'bus' => $defaultBus,
                'format' => 'qcow2',
            ];
        }

        return $normalized;
    }
}
