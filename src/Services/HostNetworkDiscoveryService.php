<?php

declare(strict_types=1);

namespace Nbkvm\Services;

class HostNetworkDiscoveryService
{
    public function detect(): array
    {
        $items = [];
        foreach (glob('/sys/class/net/*') ?: [] as $path) {
            $name = basename($path);
            if ($name === 'lo') {
                continue;
            }

            $type = $this->detectType($path);
            $items[] = [
                'name' => $name,
                'type' => $type,
                'state' => $this->readTrim($path . '/operstate') ?: 'unknown',
                'mac' => $this->readTrim($path . '/address') ?: null,
                'mtu' => $this->readTrim($path . '/mtu') ?: null,
                'speed' => $this->readTrim($path . '/speed') ?: null,
                'is_bridge' => $type === 'bridge',
            ];
        }

        usort($items, function (array $left, array $right): int {
            $leftWeight = $this->typeWeight((string) ($left['type'] ?? 'other'));
            $rightWeight = $this->typeWeight((string) ($right['type'] ?? 'other'));
            if ($leftWeight !== $rightWeight) {
                return $leftWeight <=> $rightWeight;
            }
            return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        $bridges = array_values(array_filter($items, static fn (array $item): bool => (bool) ($item['is_bridge'] ?? false)));
        $interfaces = array_values(array_filter($items, static fn (array $item): bool => !($item['is_bridge'] ?? false)));

        return [
            'preferred_bridge' => $bridges[0]['name'] ?? null,
            'bridges' => $bridges,
            'interfaces' => $interfaces,
            'all' => $items,
        ];
    }

    private function detectType(string $path): string
    {
        if (is_dir($path . '/bridge')) {
            return 'bridge';
        }

        $uevent = strtolower((string) @file_get_contents($path . '/uevent'));
        if (str_contains($uevent, 'devtype=bond')) {
            return 'bond';
        }
        if (str_contains($uevent, 'devtype=vlan')) {
            return 'vlan';
        }
        if (str_contains($uevent, 'devtype=tap')) {
            return 'tap';
        }
        if (is_link($path . '/device')) {
            return 'interface';
        }

        return 'virtual';
    }

    private function readTrim(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $value = trim((string) @file_get_contents($path));
        return $value !== '' ? $value : null;
    }

    private function typeWeight(string $type): int
    {
        return match ($type) {
            'bridge' => 0,
            'bond' => 1,
            'interface' => 2,
            'vlan' => 3,
            default => 4,
        };
    }
}
