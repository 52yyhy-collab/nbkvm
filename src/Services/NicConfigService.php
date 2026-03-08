<?php

declare(strict_types=1);

namespace Nbkvm\Services;

use Nbkvm\Repositories\IpPoolRepository;
use Nbkvm\Repositories\NetworkRepository;
use PDO;
use RuntimeException;

class NicConfigService
{
    public function __construct(private readonly ?PDO $pdo = null)
    {
    }

    private array $networkCacheById = [];
    private array $networkCacheByName = [];
    private array $networkCacheByBridge = [];
    private array $poolCacheById = [];

    public function normalizeTemplateInput(array $data, string $fallbackNetwork): array
    {
        $raw = $this->decodeCollection($data['nics_json'] ?? '[]');
        return $this->normalizeCollection($raw, $fallbackNetwork, true);
    }

    public function normalizeVmOverride(?string $json, array $fallbackNics): array
    {
        $json = trim((string) $json);
        if ($json === '') {
            return $fallbackNics;
        }
        $fallbackNetwork = trim((string) ($fallbackNics[0]['network_name'] ?? config('libvirt.default_network')));
        if ($fallbackNetwork === '') {
            $fallbackNetwork = (string) config('libvirt.default_network');
        }
        $raw = $this->decodeCollection($json);
        return $this->normalizeCollection($raw, $fallbackNetwork, true);
    }

    public function hydrateTemplateNics(array $template): array
    {
        $fallbackNetwork = trim((string) ($template['network_name'] ?? config('libvirt.default_network')));
        if ($fallbackNetwork === '') {
            $fallbackNetwork = (string) config('libvirt.default_network');
        }
        $raw = $this->decodeStoredCollection($template['nics_json'] ?? '[]');
        if ($raw === []) {
            $raw = [[
                'network_name' => $fallbackNetwork,
                'model' => 'virtio',
            ]];
        }
        return $this->normalizeCollection($raw, $fallbackNetwork, false);
    }

    public function hydrateVmNics(array $vm, ?array $template = null): array
    {
        $fallbackNetwork = trim((string) ($vm['network_name'] ?? ($template['network_name'] ?? config('libvirt.default_network'))));
        if ($fallbackNetwork === '') {
            $fallbackNetwork = (string) config('libvirt.default_network');
        }
        $raw = $this->decodeStoredCollection($vm['nics_json'] ?? '[]');
        if ($raw === []) {
            $raw = [[
                'network_name' => $fallbackNetwork,
                'model' => 'virtio',
                'ip_pool_id' => ($vm['ip_pool_id'] ?? null),
                'ip_address' => ($vm['ip_address'] ?? null),
                'family' => 'ipv4',
                'interface_name' => 'eth0',
            ]];
        }
        return $this->normalizeCollection($raw, $fallbackNetwork, false);
    }

    public function requiresCloudInitConfig(array $nics): bool
    {
        foreach ($nics as $nic) {
            if (!is_array($nic)) {
                continue;
            }
            if (in_array((string) ($nic['ipv4_mode'] ?? 'dhcp'), ['static', 'pool'], true)) {
                return true;
            }
            if (in_array((string) ($nic['ipv6_mode'] ?? 'none'), ['static', 'pool'], true)) {
                return true;
            }
        }
        return false;
    }

    public function primaryNetworkName(array $nics): string
    {
        foreach ($nics as $nic) {
            if (is_array($nic) && trim((string) ($nic['network_name'] ?? '')) !== '') {
                return trim((string) $nic['network_name']);
            }
        }
        return (string) config('libvirt.default_network');
    }

    public function primaryPoolId(array $nics): ?int
    {
        foreach ($nics as $nic) {
            if (!is_array($nic)) {
                continue;
            }
            $poolId = $this->nullableInt($nic['ipv4_pool_id'] ?? null);
            if ($poolId !== null) {
                return $poolId;
            }
        }
        foreach ($nics as $nic) {
            if (!is_array($nic)) {
                continue;
            }
            $poolId = $this->nullableInt($nic['ipv6_pool_id'] ?? null);
            if ($poolId !== null) {
                return $poolId;
            }
        }
        return null;
    }

    public function firstKnownAddress(array $nics): ?string
    {
        foreach ($nics as $nic) {
            if (!is_array($nic)) {
                continue;
            }
            $ipv4 = trim((string) ($nic['ipv4_address'] ?? ''));
            if ($ipv4 !== '') {
                return $ipv4;
            }
        }
        foreach ($nics as $nic) {
            if (!is_array($nic)) {
                continue;
            }
            $ipv6 = trim((string) ($nic['ipv6_address'] ?? ''));
            if ($ipv6 !== '') {
                return $ipv6;
            }
        }
        return null;
    }

    private function normalizeCollection(array $rawCollection, string $fallbackNetwork, bool $strict): array
    {
        $normalized = [];
        foreach (array_values($rawCollection) as $index => $rawNic) {
            if (!is_array($rawNic)) {
                continue;
            }
            $normalized[] = $this->normalizeNic($rawNic, (int) $index, $fallbackNetwork, $strict);
        }

        if ($normalized === []) {
            $normalized[] = $this->normalizeNic([
                'network_name' => $fallbackNetwork,
                'model' => 'virtio',
            ], 0, $fallbackNetwork, $strict);
        }

        return $normalized;
    }

    private function normalizeNic(array $rawNic, int $index, string $fallbackNetwork, bool $strict): array
    {
        $network = $this->resolveNetwork($rawNic, $fallbackNetwork, $strict);
        $networkName = trim((string) ($rawNic['network_name'] ?? ($network['name'] ?? $fallbackNetwork)));
        if ($networkName === '') {
            $networkName = (string) ($network['name'] ?? $fallbackNetwork);
        }

        $bridge = trim((string) ($rawNic['bridge'] ?? ($network['bridge_name'] ?? '')));
        if ($bridge === '') {
            $bridge = $networkName;
        }

        $sourceType = strtolower(trim((string) ($rawNic['source_type'] ?? ($network !== null && (int) ($network['libvirt_managed'] ?? 0) === 1 ? 'network' : 'bridge'))));
        if (!in_array($sourceType, ['bridge', 'network'], true)) {
            $sourceType = $network !== null && (int) ($network['libvirt_managed'] ?? 0) === 1 ? 'network' : 'bridge';
        }

        $sourceName = trim((string) ($rawNic['source_name'] ?? ($sourceType === 'network' ? $networkName : $bridge)));
        if ($sourceName === '') {
            $sourceName = $sourceType === 'network' ? $networkName : $bridge;
        }

        $interfaceName = trim((string) ($rawNic['interface_name'] ?? ('eth' . $index)));
        if ($interfaceName === '') {
            $interfaceName = 'eth' . $index;
        }

        $model = trim((string) ($rawNic['model'] ?? 'virtio'));
        if ($model === '') {
            $model = 'virtio';
        }

        $vlanTag = $this->nullableInt($rawNic['vlan_tag'] ?? null);
        if ($vlanTag !== null && ($vlanTag < 1 || $vlanTag > 4094)) {
            throw new RuntimeException('网卡 #' . ($index + 1) . ' 的 VLAN Tag 必须在 1-4094 之间。');
        }

        $mac = trim((string) ($rawNic['mac'] ?? ''));
        if ($mac === '') {
            $mac = null;
        } elseif (!preg_match('/^([0-9a-fA-F]{2}:){5}[0-9a-fA-F]{2}$/', $mac)) {
            throw new RuntimeException('网卡 #' . ($index + 1) . ' 的 MAC 地址不合法。');
        } else {
            $mac = strtolower($mac);
        }

        $firewall = $this->boolFlag($rawNic['firewall'] ?? false) ? 1 : 0;
        $linkDown = $this->boolFlag($rawNic['link_down'] ?? false) ? 1 : 0;

        $ipv4Mode = $this->detectIpv4Mode($rawNic, $network);
        $ipv6Mode = $this->detectIpv6Mode($rawNic, $network);

        $ipv4PoolId = $this->nullableInt($rawNic['ipv4_pool_id'] ?? $rawNic['ip_pool_id'] ?? null);
        $ipv6PoolId = $this->nullableInt($rawNic['ipv6_pool_id'] ?? null);

        $ipv4Pool = $ipv4PoolId !== null ? $this->resolvePool($ipv4PoolId, $strict) : null;
        if ($ipv4Mode === 'pool') {
            if ($ipv4PoolId === null) {
                throw new RuntimeException('网卡 #' . ($index + 1) . ' 的 IPv4 模式为 pool，但没有选择 IPv4 池。');
            }
            $this->validatePoolMatch($ipv4Pool, $network, 'ipv4', $index, $strict);
        } else {
            $ipv4PoolId = null;
            $ipv4Pool = null;
        }

        $ipv6Pool = $ipv6PoolId !== null ? $this->resolvePool($ipv6PoolId, $strict) : null;
        if ($ipv6Mode === 'pool') {
            if ($ipv6PoolId === null) {
                throw new RuntimeException('网卡 #' . ($index + 1) . ' 的 IPv6 模式为 pool，但没有选择 IPv6 池。');
            }
            $this->validatePoolMatch($ipv6Pool, $network, 'ipv6', $index, $strict);
        } else {
            $ipv6PoolId = null;
            $ipv6Pool = null;
        }

        $ipv4Address = trim((string) ($rawNic['ipv4_address'] ?? (($rawNic['family'] ?? '') === 'ipv4' ? ($rawNic['ip_address'] ?? '') : '')));
        $ipv4Prefix = $this->nullableInt($rawNic['ipv4_prefix_length'] ?? (($rawNic['family'] ?? '') === 'ipv4' ? ($rawNic['prefix_length'] ?? null) : null));
        $ipv4Gateway = trim((string) ($rawNic['ipv4_gateway'] ?? (($rawNic['family'] ?? '') === 'ipv4' ? ($rawNic['gateway'] ?? '') : '')));
        $ipv4Dns = trim((string) ($rawNic['ipv4_dns_servers'] ?? (($rawNic['family'] ?? '') === 'ipv4' ? ($rawNic['dns_servers'] ?? '') : '')));

        $networkIpv4Gateway = trim((string) ($network['gateway'] ?? ''));
        if ($ipv4Gateway === '' && $networkIpv4Gateway !== '') {
            $ipv4Gateway = $networkIpv4Gateway;
        }
        if ($ipv4Prefix === null) {
            $ipv4Prefix = $this->extractPrefix((string) ($network['cidr'] ?? '')) ?? 24;
        }
        if ($ipv4Dns === '' && $ipv4Pool !== null) {
            $ipv4Dns = trim((string) ($ipv4Pool['dns_servers'] ?? ''));
        }
        if ($ipv4Dns === '') {
            $ipv4Dns = (string) config('cloud_init.dns', '');
        }
        if ($ipv4Mode === 'static') {
            if (!filter_var($ipv4Address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                throw new RuntimeException('网卡 #' . ($index + 1) . ' 的 IPv4 静态地址不合法。');
            }
            if ($ipv4Prefix < 1 || $ipv4Prefix > 32) {
                throw new RuntimeException('网卡 #' . ($index + 1) . ' 的 IPv4 前缀长度不合法。');
            }
            if ($ipv4Gateway !== '' && !filter_var($ipv4Gateway, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                throw new RuntimeException('网卡 #' . ($index + 1) . ' 的 IPv4 网关不合法。');
            }
        } elseif ($ipv4Mode === 'pool') {
            if ($ipv4Address !== '' && !filter_var($ipv4Address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                throw new RuntimeException('网卡 #' . ($index + 1) . ' 的 IPv4 地址不合法。');
            }
            if ($ipv4Prefix < 1 || $ipv4Prefix > 32) {
                throw new RuntimeException('网卡 #' . ($index + 1) . ' 的 IPv4 前缀长度不合法。');
            }
        } else {
            $ipv4Address = null;
            $ipv4Prefix = null;
            $ipv4Gateway = null;
            $ipv4Dns = null;
        }

        $ipv6Address = trim((string) ($rawNic['ipv6_address'] ?? (($rawNic['family'] ?? '') === 'ipv6' ? ($rawNic['ip_address'] ?? '') : '')));
        $ipv6Prefix = $this->nullableInt($rawNic['ipv6_prefix_length'] ?? (($rawNic['family'] ?? '') === 'ipv6' ? ($rawNic['prefix_length'] ?? null) : null));
        $ipv6Gateway = trim((string) ($rawNic['ipv6_gateway'] ?? (($rawNic['family'] ?? '') === 'ipv6' ? ($rawNic['gateway'] ?? '') : '')));
        $ipv6Dns = trim((string) ($rawNic['ipv6_dns_servers'] ?? (($rawNic['family'] ?? '') === 'ipv6' ? ($rawNic['dns_servers'] ?? '') : '')));

        $networkIpv6Gateway = trim((string) ($network['ipv6_gateway'] ?? ''));
        if ($ipv6Gateway === '' && $networkIpv6Gateway !== '') {
            $ipv6Gateway = $networkIpv6Gateway;
        }
        if ($ipv6Prefix === null) {
            $ipv6Prefix = $this->extractPrefix((string) ($network['ipv6_cidr'] ?? '')) ?? 64;
        }
        if ($ipv6Dns === '' && $ipv6Pool !== null) {
            $ipv6Dns = trim((string) ($ipv6Pool['dns_servers'] ?? ''));
        }
        if ($ipv6Dns === '') {
            $ipv6Dns = (string) config('cloud_init.dns', '');
        }
        if ($ipv6Mode === 'static') {
            if (!filter_var($ipv6Address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                throw new RuntimeException('网卡 #' . ($index + 1) . ' 的 IPv6 静态地址不合法。');
            }
            if ($ipv6Prefix < 1 || $ipv6Prefix > 128) {
                throw new RuntimeException('网卡 #' . ($index + 1) . ' 的 IPv6 前缀长度不合法。');
            }
            if ($ipv6Gateway !== '' && !filter_var($ipv6Gateway, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                throw new RuntimeException('网卡 #' . ($index + 1) . ' 的 IPv6 网关不合法。');
            }
        } elseif ($ipv6Mode === 'pool') {
            if ($ipv6Address !== '' && !filter_var($ipv6Address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                throw new RuntimeException('网卡 #' . ($index + 1) . ' 的 IPv6 地址不合法。');
            }
            if ($ipv6Prefix < 1 || $ipv6Prefix > 128) {
                throw new RuntimeException('网卡 #' . ($index + 1) . ' 的 IPv6 前缀长度不合法。');
            }
        } elseif ($ipv6Mode === 'auto') {
            $ipv6Address = null;
            $ipv6Prefix = null;
            $ipv6Gateway = null;
            $ipv6Dns = null;
        } else {
            $ipv6Address = null;
            $ipv6Prefix = null;
            $ipv6Gateway = null;
            $ipv6Dns = null;
        }

        return [
            'interface_name' => $interfaceName,
            'network_id' => $network['id'] ?? null,
            'network_name' => $networkName,
            'bridge' => $bridge,
            'source_type' => $sourceType,
            'source_name' => $sourceName,
            'model' => $model,
            'vlan_tag' => $vlanTag,
            'mac' => $mac,
            'firewall' => $firewall,
            'link_down' => $linkDown,
            'ipv4_mode' => $ipv4Mode,
            'ipv4_pool_id' => $ipv4PoolId,
            'ipv4_address' => $ipv4Address !== '' ? $ipv4Address : null,
            'ipv4_prefix_length' => $ipv4Prefix,
            'ipv4_gateway' => $ipv4Gateway !== '' ? $ipv4Gateway : null,
            'ipv4_dns_servers' => $ipv4Dns !== '' ? $ipv4Dns : null,
            'ipv6_mode' => $ipv6Mode,
            'ipv6_pool_id' => $ipv6PoolId,
            'ipv6_address' => $ipv6Address !== '' ? $ipv6Address : null,
            'ipv6_prefix_length' => $ipv6Prefix,
            'ipv6_gateway' => $ipv6Gateway !== '' ? $ipv6Gateway : null,
            'ipv6_dns_servers' => $ipv6Dns !== '' ? $ipv6Dns : null,
        ];
    }

    private function resolveNetwork(array $rawNic, string $fallbackNetwork, bool $strict): ?array
    {
        $networkId = $this->nullableInt($rawNic['network_id'] ?? null);
        if ($networkId !== null) {
            $network = $this->findNetworkById($networkId);
            if ($network !== null) {
                return $network;
            }
        }

        $networkName = trim((string) ($rawNic['network_name'] ?? ''));
        if ($networkName !== '') {
            $network = $this->findNetworkByName($networkName);
            if ($network !== null) {
                return $network;
            }
        }

        $bridge = trim((string) ($rawNic['bridge'] ?? ''));
        if ($bridge !== '') {
            $network = $this->findNetworkByBridge($bridge);
            if ($network !== null) {
                return $network;
            }
        }

        $fallback = trim($fallbackNetwork);
        if ($fallback !== '') {
            $network = $this->findNetworkByName($fallback);
            if ($network !== null) {
                return $network;
            }
        }

        if ($strict) {
            $identifier = $networkName !== '' ? $networkName : ($bridge !== '' ? $bridge : $fallbackNetwork);
            throw new RuntimeException('找不到网卡绑定的网络：' . $identifier);
        }

        return [
            'id' => $networkId,
            'name' => $networkName !== '' ? $networkName : ($fallback !== '' ? $fallback : ($bridge !== '' ? $bridge : 'unknown-network')),
            'bridge_name' => $bridge !== '' ? $bridge : ($networkName !== '' ? $networkName : $fallback),
            'libvirt_managed' => 0,
            'cidr' => '',
            'gateway' => null,
            'ipv6_cidr' => '',
            'ipv6_gateway' => null,
        ];
    }

    private function resolvePool(int $poolId, bool $strict): ?array
    {
        if (array_key_exists($poolId, $this->poolCacheById)) {
            return $this->poolCacheById[$poolId];
        }
        $pool = (new IpPoolRepository($this->pdo))->find($poolId);
        $this->poolCacheById[$poolId] = $pool ?: null;
        if ($pool === null && $strict) {
            throw new RuntimeException('找不到 IP 池 #' . $poolId . '。');
        }
        return $pool ?: null;
    }

    private function validatePoolMatch(?array $pool, ?array $network, string $family, int $index, bool $strict): void
    {
        if ($pool === null || $network === null) {
            return;
        }
        $poolFamily = strtolower((string) ($pool['family'] ?? 'ipv4'));
        if ($poolFamily !== $family) {
            throw new RuntimeException('网卡 #' . ($index + 1) . ' 选择的 ' . strtoupper($family) . ' 池地址族不匹配。');
        }
        $networkId = isset($network['id']) ? (int) $network['id'] : 0;
        $poolNetworkId = (int) ($pool['network_id'] ?? 0);
        $poolNetworkName = trim((string) ($pool['network_name'] ?? ''));
        $networkName = trim((string) ($network['name'] ?? ''));
        if ($poolNetworkId > 0 && $networkId > 0 && $poolNetworkId !== $networkId) {
            throw new RuntimeException('网卡 #' . ($index + 1) . ' 绑定的 IP 池不属于所选网络。');
        }
        if ($poolNetworkId <= 0 && $poolNetworkName !== '' && $networkName !== '' && $poolNetworkName !== $networkName && $strict) {
            throw new RuntimeException('网卡 #' . ($index + 1) . ' 绑定的 IP 池不属于所选网络。');
        }
    }

    private function detectIpv4Mode(array $rawNic, ?array $network): string
    {
        $mode = strtolower(trim((string) ($rawNic['ipv4_mode'] ?? '')));
        if (in_array($mode, ['dhcp', 'static', 'pool', 'none'], true)) {
            return $mode;
        }
        if (($rawNic['ipv4_pool_id'] ?? $rawNic['ip_pool_id'] ?? null) !== null && ($rawNic['ipv4_pool_id'] ?? $rawNic['ip_pool_id'] ?? '') !== '') {
            return 'pool';
        }
        $legacyFamily = strtolower(trim((string) ($rawNic['family'] ?? '')));
        $legacyIp = trim((string) ($rawNic['ip_address'] ?? ''));
        if ($legacyFamily === 'ipv4' && $legacyIp !== '') {
            return 'static';
        }
        if (trim((string) ($rawNic['ipv4_address'] ?? '')) !== '') {
            return 'static';
        }
        return 'dhcp';
    }

    private function detectIpv6Mode(array $rawNic, ?array $network): string
    {
        $mode = strtolower(trim((string) ($rawNic['ipv6_mode'] ?? '')));
        if (in_array($mode, ['auto', 'static', 'pool', 'none'], true)) {
            return $mode;
        }
        if (($rawNic['ipv6_pool_id'] ?? null) !== null && ($rawNic['ipv6_pool_id'] ?? '') !== '') {
            return 'pool';
        }
        $legacyFamily = strtolower(trim((string) ($rawNic['family'] ?? '')));
        $legacyIp = trim((string) ($rawNic['ip_address'] ?? ''));
        if ($legacyFamily === 'ipv6' && $legacyIp !== '') {
            return 'static';
        }
        if (trim((string) ($rawNic['ipv6_address'] ?? '')) !== '') {
            return 'static';
        }
        if (trim((string) ($network['ipv6_cidr'] ?? '')) !== '') {
            return 'auto';
        }
        return 'none';
    }

    private function decodeCollection(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $json = trim((string) $value);
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('网卡配置 JSON 不合法。');
        }
        return $decoded;
    }

    private function decodeStoredCollection(mixed $value): array
    {
        try {
            return $this->decodeCollection($value);
        } catch (\Throwable) {
            return [];
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }
        return (int) $string;
    }

    private function boolFlag(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true) || $value === true || $value === 1;
    }

    private function extractPrefix(string $cidr): ?int
    {
        if (preg_match('/\/(\d{1,3})$/', $cidr, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    private function findNetworkById(int $id): ?array
    {
        if (array_key_exists($id, $this->networkCacheById)) {
            return $this->networkCacheById[$id];
        }
        $network = (new NetworkRepository($this->pdo))->findById($id);
        $this->cacheNetwork($network);
        return $network ?: null;
    }

    private function findNetworkByName(string $name): ?array
    {
        if (array_key_exists($name, $this->networkCacheByName)) {
            return $this->networkCacheByName[$name];
        }
        $network = (new NetworkRepository($this->pdo))->findByName($name);
        $this->cacheNetwork($network);
        return $network ?: null;
    }

    private function findNetworkByBridge(string $bridge): ?array
    {
        if (array_key_exists($bridge, $this->networkCacheByBridge)) {
            return $this->networkCacheByBridge[$bridge];
        }
        $network = (new NetworkRepository($this->pdo))->findByBridgeName($bridge);
        $this->cacheNetwork($network);
        return $network ?: null;
    }

    private function cacheNetwork(?array $network): void
    {
        if ($network === null) {
            return;
        }
        if (isset($network['id'])) {
            $this->networkCacheById[(int) $network['id']] = $network;
        }
        if (isset($network['name'])) {
            $this->networkCacheByName[(string) $network['name']] = $network;
        }
        if (!empty($network['bridge_name'])) {
            $this->networkCacheByBridge[(string) $network['bridge_name']] = $network;
        }
    }
}
