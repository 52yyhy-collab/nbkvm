<?php

declare(strict_types=1);
namespace Nbkvm\Services;
use Nbkvm\Repositories\ImageRepository;
use Nbkvm\Repositories\TemplateRepository;
use RuntimeException;
class TemplateService
{
    public function create(array $data): int
    {
        $imageId = (int) ($data['image_id'] ?? 0);
        $image = (new ImageRepository())->find($imageId);
        if (!$image) {
            throw new RuntimeException('所选镜像不存在。');
        }
        return (new TemplateRepository())->create([
            'name' => trim((string) ($data['name'] ?? '')),
            'image_id' => $imageId,
            'image_type' => $image['extension'],
            'os_variant' => trim((string) ($data['os_variant'] ?? config('libvirt.default_os_variant'))),
            'cpu' => max(1, (int) ($data['cpu'] ?? 2)),
            'memory_mb' => max(256, (int) ($data['memory_mb'] ?? 2048)),
            'disk_size_gb' => max(5, (int) ($data['disk_size_gb'] ?? 20)),
            'disk_bus' => trim((string) ($data['disk_bus'] ?? config('libvirt.default_disk_bus'))),
            'network_name' => trim((string) ($data['network_name'] ?? config('libvirt.default_network'))),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'created_at' => date('c'),
        ]);
    }
}
