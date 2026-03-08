<?php

declare(strict_types=1);
namespace Nbkvm\Services;
use Nbkvm\Repositories\ImageRepository;
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
        $image = (new ImageRepository())->find((int) $template['image_id']);
        if (!$image) {
            throw new RuntimeException('模板关联镜像不存在。');
        }
        $cpu = max(1, (int) ($data['cpu'] ?? $template['cpu']));
        $memoryMb = max(256, (int) ($data['memory_mb'] ?? $template['memory_mb']));
        $diskSizeGb = max(5, (int) ($data['disk_size_gb'] ?? $template['disk_size_gb']));
        $networkName = trim((string) ($data['network_name'] ?? $template['network_name']));
        $vmDir = rtrim((string) config('vm_path'), '/') . '/' . $name;
        if (!is_dir($vmDir)) {
            mkdir($vmDir, 493, true);
        }
        $diskPath = $vmDir . '/' . $name . '.qcow2';
        $xmlPath = $vmDir . '/' . $name . '.xml';
        $cloudInitIsoPath = null;
        $libvirt = new LibvirtService();
        $libvirt->createDiskFromImage($image['path'], $diskPath, $diskSizeGb, $image['extension']);
        @chmod($vmDir, 493);
        @chmod($diskPath, 420);
        if ((int) ($template['cloud_init_enabled'] ?? 0) === 1 && ($image['extension'] ?? '') !== 'iso') {
            $cloudInitIsoPath = (new CloudInitService())->createSeedIso(
                $name,
                (string) ($template['cloud_init_user'] ?: 'ubuntu'),
                $template['cloud_init_password'] ?: null,
                $template['cloud_init_ssh_key'] ?: null,
            );
        }
        $vm = [
            'name' => $name,
            'template_id' => $templateId,
            'cpu' => $cpu,
            'memory_mb' => $memoryMb,
            'disk_path' => $diskPath,
            'disk_size_gb' => $diskSizeGb,
            'network_name' => $networkName,
            'status' => 'defined',
            'ip_address' => null,
            'xml_path' => $xmlPath,
            'cloud_init_iso_path' => $cloudInitIsoPath,
            'vnc_display' => null,
            'created_at' => date('c'),
        ];
        $xml = (new DomainXmlBuilder())->build($vm, $template, $image);
        file_put_contents($xmlPath, $xml);
        @chmod($xmlPath, 420);
        $libvirt->define($xml);
        $vmId = $vmRepository->create($vm);
        if (($data['autostart'] ?? '') === '1') {
            $libvirt->start($name);
            $info = $libvirt->domInfo($name);
            $vmRepository->updateStatus($vmId, $libvirt->stateLabel($info['state'] ?? 1), null, $libvirt->vncDisplay($name));
        }
        return $vmId;
    }
    public function refreshStates(): void
    {
        $libvirt = new LibvirtService();
        $repo = new VmRepository();
        foreach ($repo->all() as $vm) {
            $info = $libvirt->domInfo($vm['name']);
            $repo->updateStatus((int) $vm['id'], $libvirt->stateLabel($info['state'] ?? null), null, $libvirt->vncDisplay($vm['name']));
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
        (new VmRepository())->updateStatus($id, 'running', null, $libvirt->vncDisplay($vm['name']));
    }
    public function shutdown(int $id): void
    {
        $vm = (new VmRepository())->find($id);
        if (!$vm) {
            throw new RuntimeException('虚拟机不存在。');
        }
        (new LibvirtService())->shutdown($vm['name']);
        (new VmRepository())->updateStatus($id, 'shutting down');
    }
    public function destroy(int $id): void
    {
        $vm = (new VmRepository())->find($id);
        if (!$vm) {
            throw new RuntimeException('虚拟机不存在。');
        }
        (new LibvirtService())->destroy($vm['name']);
        (new VmRepository())->updateStatus($id, 'shut off');
    }
    public function delete(int $id, bool $removeStorage = false): void
    {
        $vm = (new VmRepository())->find($id);
        if (!$vm) {
            throw new RuntimeException('虚拟机不存在。');
        }
        $libvirt = new LibvirtService();
        try {
            $libvirt->destroy($vm['name']);
        } catch (\Throwable) {
        }
        $libvirt->undefine($vm['name']);
        if ($removeStorage) {
            @unlink($vm['disk_path']);
            @unlink($vm['xml_path']);
            if (!empty($vm['cloud_init_iso_path'])) {
                @unlink((string) $vm['cloud_init_iso_path']);
            }
            @rmdir(dirname($vm['disk_path']));
        }
        (new VmRepository())->delete($id);
    }
}
