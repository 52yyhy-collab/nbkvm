<?php

declare(strict_types=1);

namespace Nbkvm\Services;

use RuntimeException;

class DiskConfigService
{
    public function normalizeTemplateInput(array $data, int $defaultSizeGb, string $defaultBus): array
    {
        $json = trim((string) ($data['disks_json_override'] ?? ''));
        if ($json === '') {
            $json = trim((string) ($data['disks_json'] ?? '[]'));
        }

        return $this->normalizeJson($json, $defaultSizeGb, $defaultBus);
    }

    public function hydrateTemplateDisks(array $template): array
    {
        $defaultSizeGb = max(5, (int) ($template['disk_size_gb'] ?? 20));
        $defaultBus = trim((string) ($template['disk_bus'] ?? config('libvirt.default_disk_bus'))) ?: (string) config('libvirt.default_disk_bus');
        $json = trim((string) ($template['disks_json'] ?? ''));

        return $this->normalizeJson($json !== '' ? $json : '[]', $defaultSizeGb, $defaultBus, false);
    }

    public function hydrateVmDisks(array $vm, ?array $template = null): array
    {
        $defaultSizeGb = max(5, (int) ($vm['disk_size_gb'] ?? ($template['disk_size_gb'] ?? 20)));
        $defaultBus = trim((string) ($template['disk_bus'] ?? config('libvirt.default_disk_bus'))) ?: (string) config('libvirt.default_disk_bus');
        $json = trim((string) ($vm['disks_json'] ?? ''));

        if ($json === '') {
            return [[
                'name' => 'disk0',
                'path' => (string) ($vm['disk_path'] ?? ''),
                'size_gb' => $defaultSizeGb,
                'bus' => $defaultBus,
                'format' => 'qcow2',
                'storage' => null,
                'ssd_emulation' => false,
                'discard' => 'ignore',
                'cache' => 'default',
                'is_primary' => true,
            ]];
        }

        return $this->normalizeJson($json, $defaultSizeGb, $defaultBus, false);
    }

    public function normalizeJson(string $json, int $defaultSizeGb, string $defaultBus, bool $strict = true): array
    {
        $decoded = [];
        if (trim($json) !== '') {
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                if ($strict) {
                    throw new RuntimeException('磁盘配置 JSON 不合法。');
                }
                $decoded = [];
            }
        }

        $normalized = [];
        $primaryIndex = null;
        $seenNames = [];

        foreach (array_values($decoded) as $index => $disk) {
            if (!is_array($disk)) {
                continue;
            }

            $name = trim((string) ($disk['name'] ?? ('disk' . $index)));
            if ($name === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
                if ($strict) {
                    throw new RuntimeException('磁盘 #' . ($index + 1) . ' 名称不合法。');
                }
                $name = 'disk' . $index;
            }
            if (isset($seenNames[$name])) {
                if ($strict) {
                    throw new RuntimeException('磁盘名称不能重复：' . $name);
                }
                $name = $name . '-' . $index;
            }
            $seenNames[$name] = true;

            $bus = strtolower(trim((string) ($disk['bus'] ?? $defaultBus)));
            if (!in_array($bus, ['virtio', 'sata', 'scsi', 'ide'], true)) {
                if ($strict) {
                    throw new RuntimeException('磁盘 #' . ($index + 1) . ' 总线类型不支持。');
                }
                $bus = $defaultBus;
            }

            $format = strtolower(trim((string) ($disk['format'] ?? 'qcow2')));
            if ($format === 'img') {
                $format = 'raw';
            }
            if (!in_array($format, ['qcow2', 'raw'], true)) {
                if ($strict) {
                    throw new RuntimeException('磁盘 #' . ($index + 1) . ' 格式不支持。');
                }
                $format = 'qcow2';
            }

            $sizeGb = max(1, (int) ($disk['size_gb'] ?? $defaultSizeGb));
            $isPrimary = $this->boolFlag($disk['is_primary'] ?? false);
            if ($primaryIndex === null && $isPrimary) {
                $primaryIndex = count($normalized);
            }

            $storage = trim((string) ($disk['storage'] ?? '')) ?: null;
            $ssdEmulation = $this->boolFlag($disk['ssd_emulation'] ?? false);
            $discard = strtolower(trim((string) ($disk['discard'] ?? 'ignore')));
            if (!in_array($discard, ['ignore', 'on', 'unmap'], true)) {
                $discard = 'ignore';
            }
            $cache = strtolower(trim((string) ($disk['cache'] ?? 'default')));
            if (!in_array($cache, ['default', 'none', 'writethrough', 'writeback', 'directsync', 'unsafe'], true)) {
                $cache = 'default';
            }

            $normalized[] = [
                'name' => $name,
                'path' => trim((string) ($disk['path'] ?? '')) ?: null,
                'size_gb' => $sizeGb,
                'bus' => $bus,
                'format' => $format,
                'storage' => $storage,
                'ssd_emulation' => $ssdEmulation,
                'discard' => $discard,
                'cache' => $cache,
                'is_primary' => $isPrimary,
            ];
        }

        if ($normalized === []) {
            $normalized[] = [
                'name' => 'disk0',
                'path' => null,
                'size_gb' => $defaultSizeGb,
                'bus' => $defaultBus,
                'format' => 'qcow2',
                'storage' => null,
                'ssd_emulation' => false,
                'discard' => 'ignore',
                'cache' => 'default',
                'is_primary' => true,
            ];
            return $normalized;
        }

        if ($primaryIndex === null) {
            $normalized[0]['is_primary'] = true;
            $primaryIndex = 0;
        }

        foreach ($normalized as $index => &$disk) {
            $disk['is_primary'] = $index === $primaryIndex;
        }
        unset($disk);

        usort($normalized, static function (array $left, array $right): int {
            return ((int) !($left['is_primary'] ?? false) <=> (int) !($right['is_primary'] ?? false));
        });

        foreach ($normalized as $index => &$disk) {
            $disk['is_primary'] = $index === 0;
        }
        unset($disk);

        return array_values($normalized);
    }

    private function boolFlag(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true) || $value === true || $value === 1;
    }
}
