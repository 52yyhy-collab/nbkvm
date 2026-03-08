<?php

declare(strict_types=1);
namespace Nbkvm\Services;
class DomainXmlBuilder
{
    public function build(array $vm, array $template, array $image): string
    {
        $name = htmlspecialchars((string) $vm['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $diskPath = htmlspecialchars((string) $vm['disk_path'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $memoryKiB = (int) $vm['memory_mb'] * 1024;
        $vcpu = (int) $vm['cpu'];
        $network = htmlspecialchars((string) $vm['network_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $diskBus = htmlspecialchars((string) $template['disk_bus'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $graphics = htmlspecialchars((string) config('libvirt.default_graphics'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $bootSection = "<boot dev='hd'/>";
        $extraDisk = '';
        if (($image['extension'] ?? '') === 'iso') {
            $isoPath = htmlspecialchars((string) $image['path'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $bootSection = "<boot dev='cdrom'/><boot dev='hd'/>";
            $extraDisk = sprintf(
                "\n    <disk type='file' device='cdrom'>\n      <driver name='qemu' type='raw'/>\n      <source file='%s'/>\n      <target dev='sda' bus='sata'/>\n      <readonly/>\n    </disk>",
                $isoPath
            );
        }
        return sprintf(
            "<domain type='kvm'>\n  <name>%s</name>\n  <memory unit='KiB'>%d</memory>\n  <currentMemory unit='KiB'>%d</currentMemory>\n  <vcpu placement='static'>%d</vcpu>\n  <os>\n    <type arch='x86_64' machine='pc'>hvm</type>\n    %s\n  </os>\n  <features>\n    <acpi/>\n    <apic/>\n  </features>\n  <cpu mode='host-model'/>\n  <clock offset='utc'/>\n  <devices>\n    <emulator>/usr/bin/qemu-system-x86_64</emulator>\n    <disk type='file' device='disk'>\n      <driver name='qemu' type='qcow2'/>\n      <source file='%s'/>\n      <target dev='vda' bus='%s'/>\n    </disk>%s\n    <interface type='network'>\n      <source network='%s'/>\n      <model type='virtio'/>\n    </interface>\n    <graphics type='%s' autoport='yes' listen='0.0.0.0'/>\n    <console type='pty'/>\n  </devices>\n</domain>",
            $name,
            $memoryKiB,
            $memoryKiB,
            $vcpu,
            $bootSection,
            $diskPath,
            $diskBus,
            $extraDisk,
            $network,
            $graphics
        );
    }
}
