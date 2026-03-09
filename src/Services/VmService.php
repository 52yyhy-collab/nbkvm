<?php

declare(strict_types=1);

namespace Nbkvm\Services;

use Nbkvm\Repositories\ImageRepository;
use Nbkvm\Repositories\IpAddressRepository;
use Nbkvm\Repositories\SettingRepository;
use Nbkvm\Repositories\TemplateRepository;
use Nbkvm\Repositories\VmRepository;
use RuntimeException;

class VmService
{
    public function createFromTemplate(array $data): int
    {
        $name = strtolower(trim((string) ($data['name'] ?? '')));
        if ($name === '' || !preg_match('/^[a-z0-9][a-z0-9-]{1,30}$/', $name)) {
            throw new RuntimeException('虚拟机名称不合法，只允许小写字母、数字和中划线，长度 2-31。');
        }

        $templateId = (int) ($data['template_id'] ?? 0);
        $template = (new TemplateRepository())->find($templateId);
        if (!$template) {
            throw new RuntimeException('模板不存在。');
        }

        $vmRepository = new VmRepository();
        if ($vmRepository->findByName($name)) {
            throw new RuntimeException('虚拟机名称已存在。');
        }

        $libvirt = new LibvirtService();
        if ($libvirt->domainExists($name)) {
            throw new RuntimeException('libvirt 中已存在同名虚拟机定义，请先清理现有 domain。');
        }

        $image = (new ImageRepository())->find((int) $template['image_id']);
        if (!$image) {
            throw new RuntimeException('模板关联镜像不存在。');
        }

        $cpu = max(1, (int) ($template['cpu'] ?? 1));
        $cpuSockets = max(1, (int) ($template['cpu_sockets'] ?? 1));
        $cpuCores = max(1, (int) ($template['cpu_cores'] ?? 1));
        $cpuThreads = max(1, (int) ($template['cpu_threads'] ?? 1));
        $memoryMb = max(256, (int) ($template['memory_mb'] ?? 2048));
        $cloudInitEnabled = (int) ($template['cloud_init_enabled'] ?? 0) === 1;

        if (($image['extension'] ?? '') === 'iso' && $cloudInitEnabled) {
            throw new RuntimeException('ISO 模板不支持 cloud-init 自动注入。');
        }

        $disks = (new DiskConfigService())->hydrateTemplateDisks($template);
        $nicService = new NicConfigService();
        $nics = $nicService->hydrateTemplateNics($template);
        $nics = $nicService->normalizeVmOverride((string) ($data['vm_nics_json'] ?? ''), $nics);
        if ($nicService->requiresCloudInitConfig($nics) && !$cloudInitEnabled) {
            throw new RuntimeException('模板网卡包含静态地址或地址池，必须启用 cloud-init。');
        }

        $vmDir = rtrim((string) config('vm_path'), '/') . '/' . $name;
        if (!is_dir($vmDir)) {
            mkdir($vmDir, 0755, true);
        }
        $xmlPath = $vmDir . '/' . $name . '.xml';
        $cloudInitIsoPath = null;
        $diskEntries = [];
        $nicConfigs = [];
        $vmId = null;
        $domainDefined = false;

        try {
            foreach (array_values($disks) as $index => $disk) {
                if (!is_array($disk)) {
                    continue;
                }
                $diskName = trim((string) ($disk['name'] ?? ('disk' . $index))) ?: ('disk' . $index);
                $sizeGb = max(1, (int) ($disk['size_gb'] ?? ($index === 0 ? 20 : 10)));
                $bus = trim((string) ($disk['bus'] ?? config('libvirt.default_disk_bus'))) ?: (string) config('libvirt.default_disk_bus');
                $format = trim((string) ($disk['format'] ?? 'qcow2')) ?: 'qcow2';
                $extension = $format === 'raw' ? 'img' : $format;
                $path = $vmDir . '/' . $diskName . '.' . $extension;

                if ($index === 0) {
                    $libvirt->createDiskFromImage((string) $image['path'], $path, $sizeGb, (string) $image['extension']);
                } else {
                    $libvirt->createEmptyDisk($path, $sizeGb, $format);
                }
                @chmod($path, 0644);
                $diskEntries[] = [
                    'name' => $diskName,
                    'path' => $path,
                    'size_gb' => $sizeGb,
                    'bus' => $bus,
                    'format' => $format,
                    'is_primary' => $index === 0,
                ];
            }

            if ($diskEntries === []) {
                throw new RuntimeException('模板没有可用磁盘配置。');
            }

            $settings = new SettingRepository();
            $primaryNetworkName = $nicService->primaryNetworkName($nics);
            $primaryPoolId = $nicService->primaryPoolId($nics);
            $vm = [
                'name' => $name,
                'template_id' => $templateId,
                'cpu' => $cpu,
                'cpu_sockets' => $cpuSockets,
                'cpu_cores' => $cpuCores,
                'cpu_threads' => $cpuThreads,
                'memory_mb' => $memoryMb,
                'disk_path' => $diskEntries[0]['path'],
                'disk_size_gb' => (int) $diskEntries[0]['size_gb'],
                'disks_json' => json_encode($diskEntries, JSON_UNESCAPED_UNICODE),
                'network_name' => $primaryNetworkName,
                'ip_pool_id' => $primaryPoolId,
                'status' => 'defined',
                'ip_address' => null,
                'xml_path' => $xmlPath,
                'cloud_init_iso_path' => null,
                'vnc_display' => null,
                'expires_at' => ($data['expires_at'] ?? '') ?: null,
                'expire_action' => 'pause',
                'expire_grace_days' => (int) ($settings->get('expire_grace_days', '3') ?: 3),
                'expired_at' => null,
                'nics_json' => json_encode($nics, JSON_UNESCAPED_UNICODE),
                'created_at' => date('c'),
            ];
            $vmId = $vmRepository->create($vm);

            $ipPoolService = new IpPoolService();
            foreach (array_values($nics) as $index => $nic) {
                if (!is_array($nic)) {
                    continue;
                }
                $nicConfig = $nic;
                $nicConfig['interface_name'] = trim((string) ($nic['interface_name'] ?? ('eth' . $index))) ?: ('eth' . $index);

                if ((string) ($nicConfig['ipv4_mode'] ?? 'dhcp') === 'pool') {
                    $allocation = $ipPoolService->allocate((int) $nicConfig['ipv4_pool_id'], $vmId);
                    if ($allocation !== null) {
                        $nicConfig['ipv4_address'] = $allocation['ip_address'];
                        $nicConfig['ipv4_prefix_length'] = $allocation['prefix_length'];
                        $nicConfig['ipv4_gateway'] = $allocation['gateway'];
                        $nicConfig['ipv4_dns_servers'] = $allocation['dns_servers'];
                    }
                }

                if ((string) ($nicConfig['ipv6_mode'] ?? 'none') === 'pool') {
                    $allocation = $ipPoolService->allocate((int) $nicConfig['ipv6_pool_id'], $vmId);
                    if ($allocation !== null) {
                        $nicConfig['ipv6_address'] = $allocation['ip_address'];
                        $nicConfig['ipv6_prefix_length'] = $allocation['prefix_length'];
                        $nicConfig['ipv6_gateway'] = $allocation['gateway'];
                        $nicConfig['ipv6_dns_servers'] = $allocation['dns_servers'];
                    }
                }

                $nicConfigs[] = $nicConfig;
            }

            $vm['ip_address'] = $nicService->firstKnownAddress($nicConfigs);
            if ($cloudInitEnabled) {
                $cloudInitIsoPath = (new CloudInitService())->createSeedIso(
                    $name,
                    (string) ($template['cloud_init_user'] ?: 'ubuntu'),
                    $template['cloud_init_password'] ?: null,
                    $template['cloud_init_ssh_key'] ?: null,
                    $nicConfigs
                );
                $vm['cloud_init_iso_path'] = $cloudInitIsoPath;
            }

            $vm['disks'] = $diskEntries;
            $vm['nics'] = $nicConfigs;
            $xml = (new DomainXmlBuilder())->build($vm, $template, $image);
            file_put_contents($xmlPath, $xml);
            @chmod($xmlPath, 0644);
            $libvirt->define($xml);
            $domainDefined = true;

            $vmRepository->updateProvisioningData($vmId, $cloudInitIsoPath, $vm['ip_address'], $nicConfigs);
            $vmRepository->updateStatus($vmId, 'defined', $vm['ip_address'], null);

            $autostart = ($data['autostart'] ?? '') === '1' || (int) ($template['autostart_default'] ?? 0) === 1;
            if ($autostart) {
                $libvirt->start($name);
                $info = $libvirt->domInfo($name);
                $vmRepository->updateStatus(
                    $vmId,
                    $libvirt->stateLabel($info['state'] ?? 1),
                    $libvirt->domIp($name) ?: $vm['ip_address'],
                    $libvirt->vncDisplay($name)
                );
            }

            return $vmId;
        } catch (\Throwable $e) {
            if ($domainDefined || $libvirt->domainExists($name)) {
                try {
                    $libvirt->destroy($name);
                } catch (\Throwable) {
                }
                try {
                    $libvirt->undefine($name);
                } catch (\Throwable) {
                }
            }
            if ($vmId !== null) {
                try {
                    (new IpPoolService())->releaseByVmId($vmId);
                } catch (\Throwable) {
                }
                try {
                    $vmRepository->delete($vmId);
                } catch (\Throwable) {
                }
            }
            try {
                (new CleanupService())->removeVmFiles([
                    'disk_path' => $diskEntries[0]['path'] ?? null,
                    'xml_path' => $xmlPath,
                    'cloud_init_iso_path' => $cloudInitIsoPath,
                ]);
            } catch (\Throwable) {
            }
            throw $e;
        }
    }

    public function update(int $id, array $data): void
    {
        $repo = new VmRepository();
        $vm = $repo->find($id);
        if (!$vm) {
            throw new RuntimeException('虚拟机不存在。');
        }

        $template = (new TemplateRepository())->find((int) ($vm['template_id'] ?? 0));
        if (!$template) {
            throw new RuntimeException('虚拟机关联模板不存在。');
        }
        $image = (new ImageRepository())->find((int) ($template['image_id'] ?? 0));
        if (!$image) {
            throw new RuntimeException('模板关联镜像不存在。');
        }

        $cpuSockets = max(1, (int) ($data['cpu_sockets'] ?? ($vm['cpu_sockets'] ?? 1)));
        $cpuCores = max(1, (int) ($data['cpu_cores'] ?? ($vm['cpu_cores'] ?? 1)));
        $cpuThreads = max(1, (int) ($data['cpu_threads'] ?? ($vm['cpu_threads'] ?? 1)));
        $cpu = $cpuSockets * $cpuCores * $cpuThreads;
        $memoryMb = max(256, (int) ($data['memory_mb'] ?? ($vm['memory_mb'] ?? 2048)));
        $expiresAt = ($data['expires_at'] ?? '') !== '' ? (string) $data['expires_at'] : null;
        $expireGraceDays = max(0, (int) ($data['expire_grace_days'] ?? ($vm['expire_grace_days'] ?? 3)));
        $expireAction = 'pause';

        $hardwareChanged = $cpu !== (int) ($vm['cpu'] ?? 0)
            || $cpuSockets !== (int) ($vm['cpu_sockets'] ?? 0)
            || $cpuCores !== (int) ($vm['cpu_cores'] ?? 0)
            || $cpuThreads !== (int) ($vm['cpu_threads'] ?? 0)
            || $memoryMb !== (int) ($vm['memory_mb'] ?? 0);

        if ($hardwareChanged && !$this->canEditHardware((string) ($vm['status'] ?? 'unknown'))) {
            throw new RuntimeException('CPU/内存仅允许在虚拟机已关机（shut off/defined）时修改。');
        }

        $diskService = new DiskConfigService();
        $nicService = new NicConfigService();
        $disks = $diskService->hydrateVmDisks($vm, $template);
        $currentNics = $nicService->hydrateVmNics($vm, $template);
        $requestedNics = array_key_exists('vm_nics_json', $data)
            ? $nicService->normalizeVmOverride((string) ($data['vm_nics_json'] ?? ''), $currentNics)
            : $currentNics;

        $networkChanged = json_encode($requestedNics, JSON_UNESCAPED_UNICODE) !== json_encode($currentNics, JSON_UNESCAPED_UNICODE);
        if ($networkChanged && !$this->canEditHardware((string) ($vm['status'] ?? 'unknown'))) {
            throw new RuntimeException('VM 网卡 / IP 配置仅允许在虚拟机已关机（shut off/defined）时修改。');
        }

        $cloudInitEnabled = (int) ($template['cloud_init_enabled'] ?? 0) === 1;
        if ($nicService->requiresCloudInitConfig($requestedNics) && !$cloudInitEnabled) {
            throw new RuntimeException('模板未启用 cloud-init，不能把 VM 网卡改成 static/pool IP 配置。');
        }

        $resolvedNics = $this->mergePoolAssignments($requestedNics, $currentNics);
        $poolTopologyChanged = $this->poolTopologyChanged($currentNics, $requestedNics)
            || $this->poolAddressesMissing($resolvedNics);
        $backupAssignments = [];
        $poolAllocationsMutated = false;

        if ($networkChanged && $poolTopologyChanged) {
            $backupAssignments = $this->poolAssignmentsFromNics($currentNics);
            $poolService = new IpPoolService();
            try {
                $poolService->releaseByVmId($id);
                $poolAllocationsMutated = true;
                $resolvedNics = $this->allocatePoolAddresses($id, $resolvedNics);
            } catch (\Throwable $e) {
                try {
                    $poolService->releaseByVmId($id);
                } catch (\Throwable) {
                }
                $this->restorePoolAssignments($id, $backupAssignments);
                throw $e;
            }
        }

        try {
            $primaryNetworkName = $nicService->primaryNetworkName($resolvedNics);
            $primaryPoolId = $nicService->primaryPoolId($resolvedNics);
            $primaryIpAddress = $nicService->firstKnownAddress($resolvedNics) ?? ($vm['ip_address'] ?? null);
            $cloudInitIsoPath = $vm['cloud_init_iso_path'] ?? null;

            if ($networkChanged && $cloudInitEnabled) {
                $cloudInitIsoPath = (new CloudInitService())->createSeedIso(
                    (string) $vm['name'],
                    (string) (($template['cloud_init_user'] ?? '') ?: 'ubuntu'),
                    ($template['cloud_init_password'] ?? null) ?: null,
                    ($template['cloud_init_ssh_key'] ?? null) ?: null,
                    $resolvedNics
                );
            }

            $payload = [
                'name' => (string) $vm['name'],
                'template_id' => (int) $vm['template_id'],
                'cpu' => $cpu,
                'cpu_sockets' => $cpuSockets,
                'cpu_cores' => $cpuCores,
                'cpu_threads' => $cpuThreads,
                'memory_mb' => $memoryMb,
                'disk_path' => (string) $vm['disk_path'],
                'disk_size_gb' => (int) ($disks[0]['size_gb'] ?? ($vm['disk_size_gb'] ?? 0)),
                'disks_json' => json_encode($disks, JSON_UNESCAPED_UNICODE),
                'network_name' => $primaryNetworkName,
                'ip_pool_id' => $primaryPoolId,
                'status' => (string) ($vm['status'] ?? 'defined'),
                'ip_address' => $primaryIpAddress,
                'xml_path' => (string) $vm['xml_path'],
                'cloud_init_iso_path' => $cloudInitIsoPath,
                'expires_at' => $expiresAt,
                'expire_action' => $expireAction,
                'expire_grace_days' => $expireGraceDays,
                'nics_json' => json_encode($resolvedNics, JSON_UNESCAPED_UNICODE),
                'disks' => $disks,
                'nics' => $resolvedNics,
            ];

            if ($hardwareChanged || $networkChanged) {
                $xml = (new DomainXmlBuilder())->build($payload, $template, $image);
                file_put_contents((string) $vm['xml_path'], $xml);
                @chmod((string) $vm['xml_path'], 0644);
                (new LibvirtService())->define($xml);
            }

            $repo->updateConfig($id, [
                'cpu' => $cpu,
                'cpu_sockets' => $cpuSockets,
                'cpu_cores' => $cpuCores,
                'cpu_threads' => $cpuThreads,
                'memory_mb' => $memoryMb,
                'disk_size_gb' => (int) ($disks[0]['size_gb'] ?? ($vm['disk_size_gb'] ?? 0)),
                'disks_json' => json_encode($disks, JSON_UNESCAPED_UNICODE),
                'network_name' => $primaryNetworkName,
                'ip_pool_id' => $primaryPoolId,
                'ip_address' => $primaryIpAddress,
                'nics_json' => json_encode($resolvedNics, JSON_UNESCAPED_UNICODE),
                'cloud_init_iso_path' => $cloudInitIsoPath,
                'expires_at' => $expiresAt,
                'expire_action' => $expireAction,
                'expire_grace_days' => $expireGraceDays,
                'xml_path' => (string) $vm['xml_path'],
            ]);
        } catch (\Throwable $e) {
            if ($poolAllocationsMutated) {
                try {
                    (new IpPoolService())->releaseByVmId($id);
                } catch (\Throwable) {
                }
                $this->restorePoolAssignments($id, $backupAssignments);
            }
            throw $e;
        }
    }

    public function refreshStates(): void
    {
        $libvirt = new LibvirtService();
        $repo = new VmRepository();
        foreach ($repo->all() as $vm) {
            $info = $libvirt->domInfo($vm['name']);
            $repo->updateStatus((int) $vm['id'], $libvirt->stateLabel($info['state'] ?? null), $libvirt->domIp($vm['name']) ?: ($vm['ip_address'] ?? null), $libvirt->vncDisplay($vm['name']));
        }
    }

    public function start(int $id): void
    {
        $vm = (new VmRepository())->find($id);
        if (!$vm) {
            throw new RuntimeException('虚拟机不存在。');
        }
        $libvirt = new LibvirtService();
        $libvirt->start($vm['name']);
        (new VmRepository())->updateStatus($id, 'running', $libvirt->domIp($vm['name']) ?: ($vm['ip_address'] ?? null), $libvirt->vncDisplay($vm['name']));
    }

    public function shutdown(int $id): void
    {
        $vm = (new VmRepository())->find($id);
        if (!$vm) {
            throw new RuntimeException('虚拟机不存在。');
        }
        (new LibvirtService())->shutdown($vm['name']);
        (new VmRepository())->updateStatus($id, 'shutting down', $vm['ip_address'] ?? null, $vm['vnc_display'] ?? null);
    }

    public function destroy(int $id): void
    {
        $vm = (new VmRepository())->find($id);
        if (!$vm) {
            throw new RuntimeException('虚拟机不存在。');
        }
        (new LibvirtService())->destroy($vm['name']);
        (new VmRepository())->updateStatus($id, 'shut off', $vm['ip_address'] ?? null, $vm['vnc_display'] ?? null);
    }

    public function delete(int $id, bool $removeStorage = false): void
    {
        $vm = (new VmRepository())->find($id);
        if (!$vm) {
            return;
        }
        $libvirt = new LibvirtService();
        try {
            $libvirt->destroy($vm['name']);
        } catch (\Throwable) {
        }
        try {
            $libvirt->undefine($vm['name']);
        } catch (\Throwable) {
        }
        if ($removeStorage) {
            (new CleanupService())->removeVmFiles($vm);
        }
        (new IpPoolService())->releaseByVmId($id);
        (new VmRepository())->delete($id);
    }

    private function mergePoolAssignments(array $requestedNics, array $currentNics): array
    {
        $merged = [];
        foreach (array_values($requestedNics) as $index => $nic) {
            if (!is_array($nic)) {
                continue;
            }
            $current = is_array($currentNics[$index] ?? null) ? $currentNics[$index] : [];

            if ((string) ($nic['ipv4_mode'] ?? 'dhcp') === 'pool'
                && (int) ($nic['ipv4_pool_id'] ?? 0) > 0
                && (int) ($current['ipv4_pool_id'] ?? 0) === (int) ($nic['ipv4_pool_id'] ?? 0)
            ) {
                foreach (['ipv4_address', 'ipv4_prefix_length', 'ipv4_gateway', 'ipv4_dns_servers'] as $field) {
                    if (empty($nic[$field]) && !empty($current[$field])) {
                        $nic[$field] = $current[$field];
                    }
                }
            }

            if ((string) ($nic['ipv6_mode'] ?? 'none') === 'pool'
                && (int) ($nic['ipv6_pool_id'] ?? 0) > 0
                && (int) ($current['ipv6_pool_id'] ?? 0) === (int) ($nic['ipv6_pool_id'] ?? 0)
            ) {
                foreach (['ipv6_address', 'ipv6_prefix_length', 'ipv6_gateway', 'ipv6_dns_servers'] as $field) {
                    if (empty($nic[$field]) && !empty($current[$field])) {
                        $nic[$field] = $current[$field];
                    }
                }
            }

            $merged[] = $nic;
        }
        return $merged;
    }

    private function poolTopologyChanged(array $currentNics, array $requestedNics): bool
    {
        $signature = static function (array $nics): string {
            $items = [];
            foreach (array_values($nics) as $nic) {
                if (!is_array($nic)) {
                    continue;
                }
                $items[] = [
                    'ipv4_mode' => (string) ($nic['ipv4_mode'] ?? 'dhcp'),
                    'ipv4_pool_id' => ($nic['ipv4_pool_id'] ?? null) !== null ? (int) $nic['ipv4_pool_id'] : null,
                    'ipv6_mode' => (string) ($nic['ipv6_mode'] ?? 'none'),
                    'ipv6_pool_id' => ($nic['ipv6_pool_id'] ?? null) !== null ? (int) $nic['ipv6_pool_id'] : null,
                ];
            }
            return (string) json_encode($items, JSON_UNESCAPED_UNICODE);
        };

        return $signature($currentNics) !== $signature($requestedNics);
    }

    private function poolAddressesMissing(array $nics): bool
    {
        foreach ($nics as $nic) {
            if (!is_array($nic)) {
                continue;
            }
            if ((string) ($nic['ipv4_mode'] ?? 'dhcp') === 'pool' && trim((string) ($nic['ipv4_address'] ?? '')) === '') {
                return true;
            }
            if ((string) ($nic['ipv6_mode'] ?? 'none') === 'pool' && trim((string) ($nic['ipv6_address'] ?? '')) === '') {
                return true;
            }
        }
        return false;
    }

    private function allocatePoolAddresses(int $vmId, array $nics): array
    {
        $poolService = new IpPoolService();
        $resolved = [];
        foreach (array_values($nics) as $index => $nic) {
            if (!is_array($nic)) {
                continue;
            }

            $nicConfig = $nic;
            $nicConfig['interface_name'] = trim((string) ($nic['interface_name'] ?? ('eth' . $index))) ?: ('eth' . $index);

            if ((string) ($nicConfig['ipv4_mode'] ?? 'dhcp') === 'pool') {
                $allocation = $poolService->allocate((int) $nicConfig['ipv4_pool_id'], $vmId);
                if ($allocation !== null) {
                    $nicConfig['ipv4_address'] = $allocation['ip_address'];
                    $nicConfig['ipv4_prefix_length'] = $allocation['prefix_length'];
                    $nicConfig['ipv4_gateway'] = $allocation['gateway'];
                    $nicConfig['ipv4_dns_servers'] = $allocation['dns_servers'];
                }
            }

            if ((string) ($nicConfig['ipv6_mode'] ?? 'none') === 'pool') {
                $allocation = $poolService->allocate((int) $nicConfig['ipv6_pool_id'], $vmId);
                if ($allocation !== null) {
                    $nicConfig['ipv6_address'] = $allocation['ip_address'];
                    $nicConfig['ipv6_prefix_length'] = $allocation['prefix_length'];
                    $nicConfig['ipv6_gateway'] = $allocation['gateway'];
                    $nicConfig['ipv6_dns_servers'] = $allocation['dns_servers'];
                }
            }

            $resolved[] = $nicConfig;
        }
        return $resolved;
    }

    private function poolAssignmentsFromNics(array $nics): array
    {
        $assignments = [];
        foreach ($nics as $nic) {
            if (!is_array($nic)) {
                continue;
            }
            if ((string) ($nic['ipv4_mode'] ?? 'dhcp') === 'pool'
                && (int) ($nic['ipv4_pool_id'] ?? 0) > 0
                && trim((string) ($nic['ipv4_address'] ?? '')) !== ''
            ) {
                $assignments[] = [
                    'pool_id' => (int) $nic['ipv4_pool_id'],
                    'ip_address' => (string) $nic['ipv4_address'],
                ];
            }
            if ((string) ($nic['ipv6_mode'] ?? 'none') === 'pool'
                && (int) ($nic['ipv6_pool_id'] ?? 0) > 0
                && trim((string) ($nic['ipv6_address'] ?? '')) !== ''
            ) {
                $assignments[] = [
                    'pool_id' => (int) $nic['ipv6_pool_id'],
                    'ip_address' => (string) $nic['ipv6_address'],
                ];
            }
        }
        return $assignments;
    }

    private function restorePoolAssignments(int $vmId, array $assignments): void
    {
        $repo = new IpAddressRepository();
        foreach ($assignments as $assignment) {
            if (!is_array($assignment)) {
                continue;
            }
            $poolId = (int) ($assignment['pool_id'] ?? 0);
            $ipAddress = trim((string) ($assignment['ip_address'] ?? ''));
            if ($poolId <= 0 || $ipAddress === '') {
                continue;
            }
            $row = $repo->findByPoolAndIp($poolId, $ipAddress);
            if ($row !== null) {
                $repo->assign((int) $row['id'], $vmId);
            }
        }
    }

    private function canEditHardware(string $status): bool
    {
        $status = strtolower($status);
        return str_contains($status, 'shut') || str_contains($status, 'defined') || str_contains($status, 'shutdown');
    }
}
