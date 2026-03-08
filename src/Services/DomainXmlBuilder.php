<?php

declare(strict_types=1);

namespace Nbkvm\Services;

class DomainXmlBuilder
{
    public function build(array $vm, array $template, array $image): string
    {
        $name = $this->e((string) $vm['name']);
        $domainType = $this->e((string) ($template['virtualization_mode'] ?? 'kvm'));
        $machineType = $this->e((string) ($template['machine_type'] ?? 'pc'));
        $memoryMb = max(256, (int) ($vm['memory_mb'] ?? 2048));
        $memoryKiB = $memoryMb * 1024;
        $memoryMaxMb = max($memoryMb, (int) (($template['memory_max_mb'] ?? 0) ?: $memoryMb));
        $memoryMaxKiB = $memoryMaxMb * 1024;
        $vcpu = max(1, (int) ($vm['cpu'] ?? 1));
        $cpuSockets = max(1, (int) ($template['cpu_sockets'] ?? 1));
        $cpuCores = max(1, (int) ($template['cpu_cores'] ?? 1));
        $cpuThreads = max(1, (int) ($template['cpu_threads'] ?? 1));
        $graphics = $this->e((string) config('libvirt.default_graphics'));
        $bootSection = "<boot dev='hd'/>";
        $osExtras = '';

        if (($template['firmware_type'] ?? 'bios') === 'uefi') {
            $loader = $this->findOvmfLoader();
            if ($loader !== null) {
                $osExtras .= "\n    <loader readonly='yes' type='pflash'>" . $this->e($loader) . '</loader>';
            }
        }

        $devices = [];
        $disks = $vm['disks'] ?? [];
        if (!is_array($disks) || $disks === []) {
            $disks = [[
                'path' => (string) $vm['disk_path'],
                'bus' => (string) ($template['disk_bus'] ?? 'virtio'),
                'format' => 'qcow2',
                'is_primary' => true,
            ]];
        }

        foreach (array_values($disks) as $index => $disk) {
            if (!is_array($disk)) {
                continue;
            }
            $path = $this->e((string) ($disk['path'] ?? ''));
            $bus = $this->e((string) ($disk['bus'] ?? 'virtio'));
            $format = $this->e((string) ($disk['format'] ?? 'qcow2'));
            $target = $this->deviceName((string) ($disk['bus'] ?? 'virtio'), $index);
            $devices[] = "    <disk type='file' device='disk'>\n      <driver name='qemu' type='{$format}'/>\n      <source file='{$path}'/>\n      <target dev='{$target}' bus='{$bus}'/>\n    </disk>";
        }

        if (($image['extension'] ?? '') === 'iso') {
            $bootSection = "<boot dev='cdrom'/><boot dev='hd'/>";
            $devices[] = "    <disk type='file' device='cdrom'>\n      <driver name='qemu' type='raw'/>\n      <source file='" . $this->e((string) $image['path']) . "'/>\n      <target dev='sda' bus='sata'/>\n      <readonly/>\n    </disk>";
        }

        if (!empty($vm['cloud_init_iso_path'])) {
            $devices[] = "    <disk type='file' device='cdrom'>\n      <driver name='qemu' type='raw'/>\n      <source file='" . $this->e((string) $vm['cloud_init_iso_path']) . "'/>\n      <target dev='sdb' bus='sata'/>\n      <readonly/>\n    </disk>";
        }

        $nics = $vm['nics'] ?? [];
        if (!is_array($nics) || $nics === []) {
            $nics = [[
                'network_name' => (string) ($vm['network_name'] ?? 'default'),
                'source_type' => 'network',
                'source_name' => (string) ($vm['network_name'] ?? 'default'),
                'model' => 'virtio',
            ]];
        }
        foreach ($nics as $nic) {
            if (!is_array($nic)) {
                continue;
            }
            $devices[] = $this->buildInterfaceDevice($nic, (string) ($vm['network_name'] ?? 'default'));
        }

        $gpuType = (string) ($template['gpu_type'] ?? 'cirrus');
        if ($gpuType !== 'none') {
            $devices[] = "    <video>\n      <model type='" . $this->e($gpuType) . "' vram='16384' heads='1' primary='yes'/>\n    </video>";
        }
        $devices[] = "    <graphics type='{$graphics}' autoport='yes' listen='0.0.0.0'/>";
        $devices[] = '    <console type=\'pty\'/>';

        $maxMemoryNode = $memoryMaxKiB > $memoryKiB ? "\n  <maxMemory slots='16' unit='KiB'>{$memoryMaxKiB}</maxMemory>" : '';

        return "<domain type='{$domainType}'>\n  <name>{$name}</name>{$maxMemoryNode}\n  <memory unit='KiB'>{$memoryKiB}</memory>\n  <currentMemory unit='KiB'>{$memoryKiB}</currentMemory>\n  <vcpu placement='static'>{$vcpu}</vcpu>\n  <cpu mode='host-model'>\n    <topology sockets='{$cpuSockets}' cores='{$cpuCores}' threads='{$cpuThreads}'/>\n  </cpu>\n  <os>\n    <type arch='x86_64' machine='{$machineType}'>hvm</type>\n    {$bootSection}{$osExtras}\n  </os>\n  <features>\n    <acpi/>\n    <apic/>\n  </features>\n  <clock offset='utc'/>\n  <devices>\n    <emulator>/usr/bin/qemu-system-x86_64</emulator>\n" . implode("\n", $devices) . "\n  </devices>\n</domain>";
    }

    private function buildInterfaceDevice(array $nic, string $fallbackNetwork): string
    {
        $sourceType = strtolower(trim((string) ($nic['source_type'] ?? '')));
        if (!in_array($sourceType, ['bridge', 'network'], true)) {
            $sourceType = !empty($nic['bridge']) && empty($nic['network_name']) ? 'bridge' : 'network';
        }

        $sourceName = trim((string) ($nic['source_name'] ?? ''));
        if ($sourceName === '') {
            $sourceName = $sourceType === 'bridge'
                ? (trim((string) ($nic['bridge'] ?? '')) ?: $fallbackNetwork)
                : (trim((string) ($nic['network_name'] ?? '')) ?: $fallbackNetwork);
        }

        $lines = [
            "    <interface type='" . $this->e($sourceType) . "'>",
            $sourceType === 'bridge'
                ? "      <source bridge='" . $this->e($sourceName) . "'/>"
                : "      <source network='" . $this->e($sourceName) . "'/>",
        ];

        $mac = trim((string) ($nic['mac'] ?? ''));
        if ($mac !== '') {
            $lines[] = "      <mac address='" . $this->e($mac) . "'/>";
        }

        $model = trim((string) ($nic['model'] ?? 'virtio')) ?: 'virtio';
        $lines[] = "      <model type='" . $this->e($model) . "'/>";

        $vlanTag = ($nic['vlan_tag'] ?? null) !== null && (string) $nic['vlan_tag'] !== '' ? (int) $nic['vlan_tag'] : null;
        if ($vlanTag !== null && $vlanTag > 0) {
            $lines[] = "      <vlan>";
            $lines[] = "        <tag id='" . $this->e((string) $vlanTag) . "'/>";
            $lines[] = "      </vlan>";
        }

        if ((int) ($nic['link_down'] ?? 0) === 1) {
            $lines[] = "      <link state='down'/>";
        }

        $lines[] = '    </interface>';
        return implode("\n", $lines);
    }

    private function deviceName(string $bus, int $index): string
    {
        $letters = range('a', 'z');
        $suffix = $letters[$index] ?? ('x' . $index);
        return match ($bus) {
            'sata', 'scsi' => 'sd' . $suffix,
            'ide' => 'hd' . $suffix,
            default => 'vd' . $suffix,
        };
    }

    private function findOvmfLoader(): ?string
    {
        foreach ([
            '/usr/share/OVMF/OVMF_CODE.fd',
            '/usr/share/OVMF/OVMF_CODE_4M.fd',
            '/usr/share/edk2/ovmf/OVMF_CODE.fd',
            '/usr/share/qemu/OVMF.fd',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
