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
        $cpuSockets = max(1, (int) ($vm['cpu_sockets'] ?? ($template['cpu_sockets'] ?? 1)));
        $cpuCores = max(1, (int) ($vm['cpu_cores'] ?? ($template['cpu_cores'] ?? 1)));
        $cpuThreads = max(1, (int) ($vm['cpu_threads'] ?? ($template['cpu_threads'] ?? 1)));
        $cpuType = trim((string) ($template['cpu_type'] ?? 'host')) ?: 'host';
        $cpuNuma = (int) ($template['cpu_numa'] ?? 0) === 1;
        $cpuLimitPercent = ($template['cpu_limit_percent'] ?? null) !== null && (string) ($template['cpu_limit_percent'] ?? '') !== ''
            ? max(1, (int) $template['cpu_limit_percent'])
            : null;
        $cpuUnits = ($template['cpu_units'] ?? null) !== null && (string) ($template['cpu_units'] ?? '') !== ''
            ? max(1, (int) $template['cpu_units'])
            : null;

        $displayType = trim((string) ($template['display_type'] ?? config('libvirt.default_graphics')));
        if (!in_array($displayType, ['vnc', 'spice', 'none'], true)) {
            $displayType = 'vnc';
        }

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
                'cache' => 'default',
                'discard' => 'ignore',
            ]];
        }

        $scsiController = trim((string) ($template['scsi_controller'] ?? 'virtio-scsi-single'));
        $hasScsiDisk = false;
        foreach ($disks as $disk) {
            if (is_array($disk) && strtolower((string) ($disk['bus'] ?? '')) === 'scsi') {
                $hasScsiDisk = true;
                break;
            }
        }
        if ($hasScsiDisk) {
            $scsiModel = $this->scsiControllerModel($scsiController);
            $devices[] = "    <controller type='scsi' index='0' model='" . $this->e($scsiModel) . "'/>";
        }

        foreach (array_values($disks) as $index => $disk) {
            if (!is_array($disk)) {
                continue;
            }

            $path = $this->e((string) ($disk['path'] ?? ''));
            $busRaw = (string) ($disk['bus'] ?? 'virtio');
            $bus = $this->e($busRaw);
            $format = $this->e((string) ($disk['format'] ?? 'qcow2'));
            $target = $this->deviceName($busRaw, $index);

            $cache = strtolower(trim((string) ($disk['cache'] ?? 'default')));
            $discard = strtolower(trim((string) ($disk['discard'] ?? 'ignore')));
            $driverAttrs = "name='qemu' type='{$format}'";
            if (in_array($cache, ['none', 'writethrough', 'writeback', 'directsync', 'unsafe'], true)) {
                $driverAttrs .= " cache='" . $this->e($cache) . "'";
            }
            if (in_array($discard, ['on', 'unmap'], true)) {
                $driverAttrs .= " discard='unmap'";
            }

            $devices[] = "    <disk type='file' device='disk'>\n"
                . "      <driver {$driverAttrs}/>\n"
                . "      <source file='{$path}'/>\n"
                . "      <target dev='{$target}' bus='{$bus}'/>\n"
                . "    </disk>";
        }

        if (($image['extension'] ?? '') === 'iso') {
            $bootSection = "<boot dev='cdrom'/><boot dev='hd'/>";
            $devices[] = "    <disk type='file' device='cdrom'>\n"
                . "      <driver name='qemu' type='raw'/>\n"
                . "      <source file='" . $this->e((string) $image['path']) . "'/>\n"
                . "      <target dev='sda' bus='sata'/>\n"
                . "      <readonly/>\n"
                . "    </disk>";
        }

        if (!empty($vm['cloud_init_iso_path'])) {
            $devices[] = "    <disk type='file' device='cdrom'>\n"
                . "      <driver name='qemu' type='raw'/>\n"
                . "      <source file='" . $this->e((string) $vm['cloud_init_iso_path']) . "'/>\n"
                . "      <target dev='sdb' bus='sata'/>\n"
                . "      <readonly/>\n"
                . "    </disk>";
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
        if ($displayType !== 'none' && $gpuType !== 'none') {
            $devices[] = "    <video>\n      <model type='" . $this->e($gpuType) . "' vram='16384' heads='1' primary='yes'/>\n    </video>";
        }

        if ($displayType !== 'none') {
            $devices[] = "    <graphics type='" . $this->e($displayType) . "' autoport='yes' listen='0.0.0.0'/>";
        }

        $serialConsoleEnabled = (int) ($template['serial_console_enabled'] ?? 1) === 1;
        if ($serialConsoleEnabled) {
            $devices[] = "    <serial type='pty'>\n      <target port='0'/>\n    </serial>";
            $devices[] = "    <console type='pty'>\n      <target type='serial' port='0'/>\n    </console>";
        }

        $qemuAgentEnabled = (int) ($template['qemu_agent_enabled'] ?? 1) === 1;
        if ($qemuAgentEnabled) {
            $devices[] = "    <channel type='unix'>\n      <target type='virtio' name='org.qemu.guest_agent.0'/>\n    </channel>";
        }

        $balloonEnabled = (int) ($template['balloon_enabled'] ?? 1) === 1;
        $devices[] = "    <memballoon model='" . ($balloonEnabled ? 'virtio' : 'none') . "'/>";

        $maxMemoryNode = $memoryMaxKiB > $memoryKiB ? "\n  <maxMemory slots='16' unit='KiB'>{$memoryMaxKiB}</maxMemory>" : '';
        $cpuSection = $this->buildCpuSection($cpuType, $cpuSockets, $cpuCores, $cpuThreads, $cpuNuma, $vcpu, $memoryKiB);
        $cpuTuneSection = $this->buildCpuTuneSection($cpuLimitPercent, $cpuUnits, $vcpu);

        return "<domain type='{$domainType}'>\n"
            . "  <name>{$name}</name>{$maxMemoryNode}\n"
            . "  <memory unit='KiB'>{$memoryKiB}</memory>\n"
            . "  <currentMemory unit='KiB'>{$memoryKiB}</currentMemory>\n"
            . "  <vcpu placement='static'>{$vcpu}</vcpu>\n"
            . $cpuSection
            . $cpuTuneSection
            . "  <os>\n"
            . "    <type arch='x86_64' machine='{$machineType}'>hvm</type>\n"
            . "    {$bootSection}{$osExtras}\n"
            . "  </os>\n"
            . "  <features>\n"
            . "    <acpi/>\n"
            . "    <apic/>\n"
            . "  </features>\n"
            . "  <clock offset='utc'/>\n"
            . "  <devices>\n"
            . "    <emulator>/usr/bin/qemu-system-x86_64</emulator>\n"
            . implode("\n", $devices)
            . "\n  </devices>\n"
            . "</domain>";
    }

    private function buildCpuSection(string $cpuType, int $sockets, int $cores, int $threads, bool $numa, int $vcpu, int $memoryKiB): string
    {
        $type = strtolower(trim($cpuType));
        if ($type === '' || $type === 'host' || $type === 'host-passthrough') {
            $mode = 'host-passthrough';
            $extra = "";
        } elseif ($type === 'default' || $type === 'host-model') {
            $mode = 'host-model';
            $extra = "";
        } else {
            $mode = 'custom';
            $extra = "\n    <model fallback='allow'>" . $this->e($cpuType) . "</model>";
        }

        $numaXml = '';
        if ($numa) {
            $numaXml = "\n    <numa>\n      <cell id='0' cpus='0-" . max(0, $vcpu - 1) . "' memory='{$memoryKiB}' unit='KiB'/>\n    </numa>";
        }

        $matchAttr = $mode === 'custom' ? " match='exact'" : '';
        $checkAttr = $mode === 'host-passthrough' ? " check='none'" : '';

        return "  <cpu mode='{$mode}'{$matchAttr}{$checkAttr}>\n"
            . "    <topology sockets='{$sockets}' cores='{$cores}' threads='{$threads}'/>"
            . $extra
            . $numaXml
            . "\n  </cpu>\n";
    }

    private function buildCpuTuneSection(?int $cpuLimitPercent, ?int $cpuUnits, int $vcpu): string
    {
        if ($cpuLimitPercent === null && $cpuUnits === null) {
            return '';
        }

        $lines = ['  <cputune>'];
        if ($cpuLimitPercent !== null) {
            $period = 100000;
            $quota = (int) floor($period * ($cpuLimitPercent / 100) * max(1, $vcpu));
            $lines[] = "    <period>{$period}</period>";
            $lines[] = "    <quota>{$quota}</quota>";
        }
        if ($cpuUnits !== null) {
            $lines[] = '    <shares>' . max(2, min(262144, $cpuUnits)) . '</shares>';
        }
        $lines[] = '  </cputune>';

        return implode("\n", $lines) . "\n";
    }

    private function scsiControllerModel(string $controller): string
    {
        return match (strtolower(trim($controller))) {
            'lsi' => 'lsilogic',
            'megasas' => 'megasas',
            default => 'virtio-scsi',
        };
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
            $lines[] = '      <vlan>';
            $lines[] = "        <tag id='" . $this->e((string) $vlanTag) . "'/>";
            $lines[] = '      </vlan>';
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
