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
        $payload = $this->buildPayload($data);
        return (new TemplateRepository())->create($payload + [
            'created_at' => date('c'),
        ]);
    }

    public function update(int $id, array $data): void
    {
        $repo = new TemplateRepository();
        $existing = $repo->find($id);
        if (!$existing) {
            throw new RuntimeException('模板不存在。');
        }

        $payload = $this->buildPayload($data, $existing);
        $this->assertSafeUpdate($existing, $payload);
        $repo->update($id, $payload);
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

    private function buildPayload(array $data, ?array $existing = null): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('模板名称不能为空。');
        }

        $duplicate = (new TemplateRepository())->findByName($name);
        if ($duplicate !== null && (int) ($duplicate['id'] ?? 0) !== (int) ($existing['id'] ?? 0)) {
            throw new RuntimeException('模板名称已存在。');
        }

        $imageId = (int) ($data['image_id'] ?? 0);
        $image = (new ImageRepository())->find($imageId);
        if (!$image) {
            throw new RuntimeException('所选镜像不存在。');
        }

        $cpuSockets = max(1, (int) ($data['cpu_sockets'] ?? ($existing['cpu_sockets'] ?? 1)));
        $cpuCores = max(1, (int) ($data['cpu_cores'] ?? ($existing['cpu_cores'] ?? 1)));
        $cpuThreads = max(1, (int) ($data['cpu_threads'] ?? ($existing['cpu_threads'] ?? 1)));
        $cpu = $cpuSockets * $cpuCores * $cpuThreads;

        $fallbackNetwork = trim((string) ($data['network_name'] ?? $existing['network_name'] ?? config('libvirt.default_network')));
        if ($fallbackNetwork === '') {
            $fallbackNetwork = (string) config('libvirt.default_network');
        }

        $memoryMb = max(256, (int) ($data['memory_mb'] ?? ($existing['memory_mb'] ?? 2048)));
        $memoryMaxMb = ($data['memory_max_mb'] ?? '') !== ''
            ? max($memoryMb, (int) $data['memory_max_mb'])
            : (($existing['memory_max_mb'] ?? null) !== null ? max($memoryMb, (int) $existing['memory_max_mb']) : null);
        $diskSizeGb = max(5, (int) ($data['disk_size_gb'] ?? ($existing['disk_size_gb'] ?? 20)));
        $diskBus = trim((string) ($data['disk_bus'] ?? ($existing['disk_bus'] ?? config('libvirt.default_disk_bus'))));
        if ($diskBus === '') {
            $diskBus = (string) config('libvirt.default_disk_bus');
        }

        $cloudInitEnabled = ($data['cloud_init_enabled'] ?? '') === '1' ? 1 : 0;
        $disks = (new DiskConfigService())->normalizeTemplateInput($data, $diskSizeGb, $diskBus);
        $nics = (new NicConfigService())->normalizeTemplateInput($data, $fallbackNetwork);
        if ((new NicConfigService())->requiresCloudInitConfig($nics) && $cloudInitEnabled !== 1) {
            throw new RuntimeException('模板网卡包含静态地址或地址池，必须启用 cloud-init。');
        }
        $primaryNetworkName = (new NicConfigService())->primaryNetworkName($nics);

        $osVariant = trim((string) ($data['os_variant'] ?? ($existing['os_variant'] ?? config('libvirt.default_os_variant'))));
        if ($osVariant === '') {
            $osVariant = (string) config('libvirt.default_os_variant');
        }

        $vmidHintRaw = trim((string) ($data['vmid_hint'] ?? ($existing['vmid_hint'] ?? '')));
        $vmidHint = $vmidHintRaw !== '' ? max(1, (int) $vmidHintRaw) : null;

        $osType = trim((string) ($data['os_type'] ?? ($existing['os_type'] ?? 'l26')));
        if (!in_array($osType, ['l26', 'win', 'other'], true)) {
            $osType = 'l26';
        }

        $defaultOsSource = strtolower((string) ($image['extension'] ?? '')) === 'iso' ? 'iso' : 'image';
        $osSource = trim((string) ($data['os_source'] ?? ($existing['os_source'] ?? $defaultOsSource)));
        if (!in_array($osSource, ['image', 'iso', 'clone'], true)) {
            $osSource = $defaultOsSource;
        }

        $cpuType = trim((string) ($data['cpu_type'] ?? ($existing['cpu_type'] ?? 'host')));
        if ($cpuType === '') {
            $cpuType = 'host';
        }
        $cpuNuma = $this->boolFlag($data['cpu_numa'] ?? ($existing['cpu_numa'] ?? 0)) ? 1 : 0;
        $cpuLimitPercent = ($data['cpu_limit_percent'] ?? '') !== ''
            ? max(1, (int) $data['cpu_limit_percent'])
            : (($existing['cpu_limit_percent'] ?? null) !== null ? max(1, (int) $existing['cpu_limit_percent']) : null);
        $cpuUnits = ($data['cpu_units'] ?? '') !== ''
            ? max(1, (int) $data['cpu_units'])
            : (($existing['cpu_units'] ?? null) !== null ? max(1, (int) $existing['cpu_units']) : null);

        $memoryMinMb = ($data['memory_min_mb'] ?? '') !== ''
            ? min($memoryMb, max(128, (int) $data['memory_min_mb']))
            : (($existing['memory_min_mb'] ?? null) !== null ? min($memoryMb, max(128, (int) $existing['memory_min_mb'])) : null);
        $balloonEnabled = $this->boolFlag($data['balloon_enabled'] ?? ($existing['balloon_enabled'] ?? 1)) ? 1 : 0;

        $scsiController = trim((string) ($data['scsi_controller'] ?? ($existing['scsi_controller'] ?? 'virtio-scsi-single')));
        if ($scsiController === '') {
            $scsiController = 'virtio-scsi-single';
        }
        $qemuAgentEnabled = $this->boolFlag($data['qemu_agent_enabled'] ?? ($existing['qemu_agent_enabled'] ?? 1)) ? 1 : 0;
        $displayType = trim((string) ($data['display_type'] ?? ($existing['display_type'] ?? 'vnc')));
        if (!in_array($displayType, ['vnc', 'spice', 'none'], true)) {
            $displayType = 'vnc';
        }
        $serialConsoleEnabled = $this->boolFlag($data['serial_console_enabled'] ?? ($existing['serial_console_enabled'] ?? 1)) ? 1 : 0;

        $cloudInitUser = trim((string) ($data['cloud_init_user'] ?? ($existing['cloud_init_user'] ?? 'ubuntu')));
        if ($cloudInitUser === '') {
            $cloudInitUser = 'ubuntu';
        }

        return [
            'name' => $name,
            'vmid_hint' => $vmidHint,
            'image_id' => $imageId,
            'image_type' => $image['extension'],
            'os_type' => $osType,
            'os_source' => $osSource,
            'os_variant' => $osVariant,
            'cpu' => $cpu,
            'cpu_type' => $cpuType,
            'cpu_numa' => $cpuNuma,
            'cpu_limit_percent' => $cpuLimitPercent,
            'cpu_units' => $cpuUnits,
            'memory_mb' => $memoryMb,
            'memory_min_mb' => $memoryMinMb,
            'memory_max_mb' => $memoryMaxMb,
            'balloon_enabled' => $balloonEnabled,
            'disk_size_gb' => (int) ($disks[0]['size_gb'] ?? $diskSizeGb),
            'disk_bus' => (string) ($disks[0]['bus'] ?? $diskBus),
            'scsi_controller' => $scsiController,
            'network_name' => $primaryNetworkName,
            'notes' => trim((string) ($data['notes'] ?? ($existing['notes'] ?? ''))),
            'cloud_init_enabled' => $cloudInitEnabled,
            'cloud_init_user' => $cloudInitUser,
            'cloud_init_password' => trim((string) ($data['cloud_init_password'] ?? ($existing['cloud_init_password'] ?? ''))) ?: null,
            'cloud_init_ssh_key' => trim((string) ($data['cloud_init_ssh_key'] ?? ($existing['cloud_init_ssh_key'] ?? ''))) ?: null,
            'cloud_init_hostname' => trim((string) ($data['cloud_init_hostname'] ?? ($existing['cloud_init_hostname'] ?? ''))) ?: null,
            'cloud_init_dns_servers' => trim((string) ($data['cloud_init_dns_servers'] ?? ($existing['cloud_init_dns_servers'] ?? ''))) ?: null,
            'cloud_init_search_domain' => trim((string) ($data['cloud_init_search_domain'] ?? ($existing['cloud_init_search_domain'] ?? ''))) ?: null,
            'cloud_init_extra_user_data' => trim((string) ($data['cloud_init_extra_user_data'] ?? ($existing['cloud_init_extra_user_data'] ?? ''))) ?: null,
            'virtualization_mode' => trim((string) ($data['virtualization_mode'] ?? ($existing['virtualization_mode'] ?? config('virtualization.default_mode')))),
            'cpu_sockets' => $cpuSockets,
            'cpu_cores' => $cpuCores,
            'cpu_threads' => $cpuThreads,
            'machine_type' => trim((string) ($data['machine_type'] ?? ($existing['machine_type'] ?? config('virtualization.default_machine')))),
            'firmware_type' => trim((string) ($data['firmware_type'] ?? ($existing['firmware_type'] ?? config('virtualization.default_firmware')))),
            'qemu_agent_enabled' => $qemuAgentEnabled,
            'display_type' => $displayType,
            'serial_console_enabled' => $serialConsoleEnabled,
            'gpu_type' => trim((string) ($data['gpu_type'] ?? ($existing['gpu_type'] ?? config('virtualization.default_gpu')))),
            'autostart_default' => ($data['autostart_default'] ?? '') === '1' ? 1 : 0,
            'memory_overcommit_percent' => max(100, (int) ($data['memory_overcommit_percent'] ?? ($existing['memory_overcommit_percent'] ?? 100))),
            'disk_overcommit_enabled' => ($data['disk_overcommit_enabled'] ?? '') === '1' ? 1 : 0,
            'disks_json' => json_encode($disks, JSON_UNESCAPED_UNICODE),
            'nics_json' => json_encode($nics, JSON_UNESCAPED_UNICODE),
        ];
    }

    private function assertSafeUpdate(array $existing, array $payload): void
    {
        if ((int) ($existing['linked_vm_count'] ?? 0) <= 0) {
            return;
        }

        $lockedFields = [
            'image_id' => '基础镜像',
            'virtualization_mode' => '虚拟化模式',
            'machine_type' => '主板类型',
            'firmware_type' => '固件类型',
        ];

        foreach ($lockedFields as $field => $label) {
            if (($existing[$field] ?? null) !== ($payload[$field] ?? null)) {
                throw new RuntimeException('该模板已经派生出虚拟机，不能再修改“' . $label . '”。如需变更，请复制出新模板。');
            }
        }
    }

    private function boolFlag(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true) || $value === true || $value === 1;
    }
}
