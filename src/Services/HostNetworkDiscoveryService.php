<?php

declare(strict_types=1);

namespace Nbkvm\Services;

class HostNetworkDiscoveryService
{
    public function detect(): array
    {
        $items = [];
        $linkDetails = $this->indexByIfName($this->readJsonCommand('ip -j -d link show 2>/dev/null'));
        $addressDetails = $this->indexByIfName($this->readJsonCommand('ip -j address show 2>/dev/null'));
        $defaultRoutes = $this->defaultRouteMap($this->readJsonCommand('ip -j route show default 2>/dev/null'));

        foreach (glob('/sys/class/net/*') ?: [] as $path) {
            $name = basename($path);
            if ($name === 'lo') {
                continue;
            }

            $link = $linkDetails[$name] ?? [];
            $address = $addressDetails[$name] ?? [];
            $type = $this->detectType($path, $link);
            $ports = $this->detectPorts($path, $type);
            $master = $this->detectMaster($path, $link);
            $parent = $this->detectParent($path, $link, $type);
            $addresses = $this->normalizeAddresses($address['addr_info'] ?? []);

            $items[] = [
                'name' => $name,
                'type' => $type,
                'type_label' => $this->typeLabel($type),
                'kind' => $this->linkKind($link),
                'state' => $this->readTrim($path . '/operstate') ?: 'unknown',
                'mac' => $this->readTrim($path . '/address') ?: null,
                'mtu' => $this->readTrim($path . '/mtu') ?: null,
                'speed' => $this->sanitizeMetric($this->readTrim($path . '/speed')),
                'duplex' => $this->readTrim($path . '/duplex') ?: null,
                'carrier' => $this->readTrim($path . '/carrier') ?: null,
                'master' => $master,
                'parent' => $parent,
                'ports' => $ports,
                'uppers' => $this->detectUppers($path),
                'addresses' => $addresses,
                'default_routes' => $defaultRoutes[$name] ?? [],
                'vlan_id' => $this->detectVlanId($path, $link),
                'is_bridge' => $type === 'bridge',
                'is_physical' => $type === 'physical',
                'is_vmbr' => str_starts_with($name, 'vmbr'),
            ];
        }

        usort($items, function (array $left, array $right): int {
            $leftWeight = $this->typeWeight((string) ($left['type'] ?? 'other'), (string) ($left['name'] ?? ''));
            $rightWeight = $this->typeWeight((string) ($right['type'] ?? 'other'), (string) ($right['name'] ?? ''));
            if ($leftWeight !== $rightWeight) {
                return $leftWeight <=> $rightWeight;
            }
            return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        $bridges = array_values(array_filter($items, static fn (array $item): bool => (string) ($item['type'] ?? '') === 'bridge'));
        $interfaces = array_values(array_filter($items, static fn (array $item): bool => (string) ($item['type'] ?? '') !== 'bridge'));

        $preferredBridge = null;
        foreach ($bridges as $bridge) {
            if (!empty($bridge['is_vmbr'])) {
                $preferredBridge = (string) $bridge['name'];
                break;
            }
        }
        if ($preferredBridge === null) {
            $preferredBridge = $bridges[0]['name'] ?? null;
        }

        return [
            'preferred_bridge' => $preferredBridge,
            'bridges' => $bridges,
            'interfaces' => $interfaces,
            'all' => $items,
        ];
    }

    private function detectType(string $path, array $link): string
    {
        $kind = $this->linkKind($link);
        if ($kind === 'bridge' || is_dir($path . '/bridge')) {
            return 'bridge';
        }
        if ($kind === 'bond') {
            return 'bond';
        }
        if ($kind === 'vlan') {
            return 'vlan';
        }
        if (in_array($kind, ['tap', 'tun'], true)) {
            return 'tap';
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
            return 'physical';
        }

        return 'virtual';
    }

    private function detectPorts(string $path, string $type): array
    {
        if ($type === 'bridge') {
            return $this->listSymlinkBasenames($path . '/brif/*');
        }

        if ($type === 'bond') {
            $slaves = preg_split('/\s+/', (string) ($this->readTrim($path . '/bonding/slaves') ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            return array_values(array_map('strval', $slaves ?: []));
        }

        return [];
    }

    private function detectMaster(string $path, array $link): ?string
    {
        if (!empty($link['master']) && is_string($link['master'])) {
            return $link['master'];
        }

        $master = @readlink($path . '/master');
        if ($master !== false) {
            return basename($master);
        }

        return null;
    }

    private function detectParent(string $path, array $link, string $type): ?string
    {
        $lower = $this->listSymlinkBasenames($path . '/lower_*', 'lower_');
        if ($lower !== []) {
            return $lower[0];
        }

        if (($type === 'vlan' || $type === 'tap') && !empty($link['link']) && is_string($link['link'])) {
            return $link['link'];
        }

        return null;
    }

    private function detectUppers(string $path): array
    {
        return $this->listSymlinkBasenames($path . '/upper_*', 'upper_');
    }

    private function detectVlanId(string $path, array $link): ?int
    {
        $linkInfo = $link['linkinfo']['info_data']['id'] ?? null;
        if ($linkInfo !== null && is_numeric($linkInfo)) {
            return (int) $linkInfo;
        }

        $vlanFile = '/proc/net/vlan/' . basename($path);
        if (is_file($vlanFile)) {
            $content = (string) @file_get_contents($vlanFile);
            if (preg_match('/VID:\s*(\d+)/', $content, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    private function normalizeAddresses(array $addrInfo): array
    {
        $addresses = [];
        foreach ($addrInfo as $row) {
            if (!is_array($row)) {
                continue;
            }
            $local = trim((string) ($row['local'] ?? ''));
            if ($local === '') {
                continue;
            }
            $prefix = isset($row['prefixlen']) ? (int) $row['prefixlen'] : null;
            $family = strtolower(trim((string) ($row['family'] ?? '')));
            $scope = trim((string) ($row['scope'] ?? ''));
            $label = $local;
            if ($prefix !== null) {
                $label .= '/' . $prefix;
            }
            if ($family !== '') {
                $label .= ' (' . $family;
                if ($scope !== '') {
                    $label .= ', ' . $scope;
                }
                $label .= ')';
            }
            $addresses[] = $label;
        }
        return array_values(array_unique($addresses));
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'bridge' => 'Bridge',
            'bond' => 'Bond',
            'vlan' => 'VLAN',
            'physical' => 'Physical',
            'tap' => 'Tap/Tun',
            default => 'Virtual',
        };
    }

    private function typeWeight(string $type, string $name): int
    {
        if ($type === 'bridge' && str_starts_with($name, 'vmbr')) {
            return 0;
        }

        return match ($type) {
            'bridge' => 1,
            'bond' => 2,
            'vlan' => 3,
            'physical' => 4,
            'tap' => 5,
            default => 6,
        };
    }

    private function linkKind(array $link): string
    {
        return strtolower(trim((string) ($link['linkinfo']['info_kind'] ?? '')));
    }

    private function defaultRouteMap(array $routes): array
    {
        $map = [];
        foreach ($routes as $route) {
            if (!is_array($route)) {
                continue;
            }
            $dev = trim((string) ($route['dev'] ?? ''));
            if ($dev === '') {
                continue;
            }
            $gateway = trim((string) ($route['gateway'] ?? ''));
            $label = $gateway !== '' ? $gateway : 'on-link';
            $map[$dev][] = $label;
        }

        foreach ($map as $dev => $entries) {
            $map[$dev] = array_values(array_unique(array_map('strval', $entries)));
        }

        return $map;
    }

    private function indexByIfName(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['ifname'] ?? ''));
            if ($name === '') {
                continue;
            }
            $indexed[$name] = $row;
        }
        return $indexed;
    }

    private function readJsonCommand(string $command): array
    {
        $output = @shell_exec($command);
        if (!is_string($output) || trim($output) === '') {
            return [];
        }

        $decoded = json_decode($output, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function listSymlinkBasenames(string $pattern, string $trimPrefix = ''): array
    {
        $items = [];
        foreach (glob($pattern) ?: [] as $path) {
            $name = basename($path);
            if ($trimPrefix !== '' && str_starts_with($name, $trimPrefix)) {
                $name = substr($name, strlen($trimPrefix));
            }
            $items[] = $name;
        }
        sort($items);
        return array_values(array_unique($items));
    }

    private function sanitizeMetric(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return null;
        }
        return $value;
    }

    private function readTrim(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $value = trim((string) @file_get_contents($path));
        return $value !== '' ? $value : null;
    }
}
