<?php

declare(strict_types=1);

namespace Nbkvm\Services;

use Nbkvm\Repositories\IpPoolRepository;
use Nbkvm\Repositories\NetworkRepository;
use Nbkvm\Repositories\TemplateRepository;
use Nbkvm\Repositories\VmRepository;
use RuntimeException;

class NetworkService
{
    public function create(array $data): int
    {
        $payload = $this->normalizeNetworkPayload($data);
        return (new NetworkRepository())->create($payload + [
            'created_at' => date('c'),
        ]);
    }

    public function update(int $id, array $data): void
    {
        $repo = new NetworkRepository();
        $existing = $repo->findById($id);
        if (!$existing) {
            throw new RuntimeException('网络不存在。');
        }

        $payload = $this->normalizeNetworkPayload($data, (string) $existing['name']);
        $oldName = (string) $existing['name'];
        $newName = (string) $payload['name'];

        if ($oldName !== $newName) {
            $this->assertNetworkRenameAllowed($existing);
        }
        $this->assertDangerousNetworkChangesAllowed($existing, $payload);

        $repo->updateById($id, $payload + [
            'autostart' => (int) ($existing['autostart'] ?? 1),
        ]);
        $this->syncPoolNetworkName($id, $newName);
    }

    public function saveWithPool(array $data): array
    {
        $repo = new NetworkRepository();
        $networkId = (int) ($data['network_id'] ?? 0);
        if ($networkId > 0) {
            $this->update($networkId, $data);
        } else {
            $networkId = $this->create($data);
        }

        $network = $repo->findById($networkId);
        if (!$network) {
            throw new RuntimeException('网络保存成功，但回读失败。');
        }

        $this->syncInlinePoolFamily($network, $data, 'ipv4');
        $this->syncInlinePoolFamily($network, $data, 'ipv6');

        return [
            'network_id' => $networkId,
            'network' => $network,
        ];
    }

    public function delete(string|int $idOrName): void
    {
        $repo = new NetworkRepository();
        $network = is_int($idOrName) ? $repo->findById($idOrName) : $repo->findByName((string) $idOrName);
        if (!$network) {
            return;
        }

        $this->assertNetworkDeleteAllowed($network);

        $poolService = new IpPoolService();
        $poolRepo = new IpPoolRepository();
        $networkId = (int) ($network['id'] ?? 0);
        $pools = $networkId > 0
            ? $poolRepo->findAllByNetworkId($networkId)
            : $poolRepo->findAllByNetworkName((string) $network['name']);
        foreach ($pools as $pool) {
            $poolService->delete((int) $pool['id']);
        }

        $repo->deleteById((int) $network['id']);
    }

    public function findForPool(?int $networkId, ?string $networkName): array
    {
        $repo = new NetworkRepository();
        $network = null;
        if ($networkId !== null && $networkId > 0) {
            $network = $repo->findById($networkId);
        }
        if ($network === null && $networkName !== null && trim($networkName) !== '') {
            $network = $repo->findByName(trim($networkName));
        }
        if ($network === null) {
            throw new RuntimeException('请选择有效的网络。');
        }
        return $network;
    }

    public function assertPoolMatchesNetwork(array $network, string $startIp, string $endIp, string $family = 'ipv4'): void
    {
        $family = strtolower($family);
        if (!in_array($family, ['ipv4', 'ipv6'], true)) {
            throw new RuntimeException('IP 池地址族只支持 ipv4 或 ipv6。');
        }

        if ($family === 'ipv6') {
            $cidr = trim((string) ($network['ipv6_cidr'] ?? ''));
            if ($cidr === '') {
                throw new RuntimeException('所选网络未配置 IPv6 子网，不能创建 IPv6 地址池。');
            }
            if (!preg_match('/^([0-9a-fA-F:]+)\/(\d{1,3})$/', $cidr, $matches)) {
                throw new RuntimeException('所选网络的 IPv6 子网格式不合法。');
            }
            $networkBase = $matches[1];
            $networkPrefix = (int) $matches[2];
            foreach ([$startIp, $endIp] as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    throw new RuntimeException('IPv6 地址池范围不合法。');
                }
                if (!$this->ipv6InSubnet($ip, $networkBase, $networkPrefix)) {
                    throw new RuntimeException('IPv6 IP 池地址范围不在所选网络内。');
                }
            }
            if ($this->compareIpv6($startIp, $endIp) > 0) {
                throw new RuntimeException('IPv6 IP 池起始地址不能大于结束地址。');
            }
            return;
        }

        $cidr = trim((string) ($network['cidr'] ?? ''));
        if ($cidr === '') {
            throw new RuntimeException('所选网络未配置 IPv4 子网，不能创建 IPv4 地址池。');
        }
        if (!preg_match('/^(\d+\.\d+\.\d+\.\d+)\/(\d{1,2})$/', $cidr, $matches)) {
            throw new RuntimeException('所选网络的 IPv4 子网格式不合法。');
        }
        $networkBase = $matches[1];
        $networkPrefix = (int) $matches[2];
        foreach ([$startIp, $endIp] as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                throw new RuntimeException('IPv4 地址池范围不合法。');
            }
            if (!$this->ipInSubnet($ip, $networkBase, $networkPrefix)) {
                throw new RuntimeException('IPv4 IP 池地址范围不在所选网络内。');
            }
        }
        if ((int) ip2long($startIp) > (int) ip2long($endIp)) {
            throw new RuntimeException('IPv4 IP 池起始地址不能大于结束地址。');
        }
    }

    public function prefixForFamily(array $network, string $family): ?int
    {
        $cidr = strtolower($family) === 'ipv6'
            ? trim((string) ($network['ipv6_cidr'] ?? ''))
            : trim((string) ($network['cidr'] ?? ''));
        if (preg_match('/\/(\d{1,3})$/', $cidr, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    public function gatewayForFamily(array $network, string $family): ?string
    {
        $gateway = strtolower($family) === 'ipv6'
            ? trim((string) ($network['ipv6_gateway'] ?? ''))
            : trim((string) ($network['gateway'] ?? ''));
        return $gateway !== '' ? $gateway : null;
    }

    private function normalizeNetworkPayload(array $data, ?string $currentName = null): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $cidr = trim((string) ($data['cidr'] ?? ''));
        $gateway = trim((string) ($data['gateway'] ?? ''));
        $bridge = trim((string) ($data['bridge_name'] ?? ''));
        $dhcpStart = trim((string) ($data['dhcp_start'] ?? ''));
        $dhcpEnd = trim((string) ($data['dhcp_end'] ?? ''));
        $ipv6Cidr = trim((string) ($data['ipv6_cidr'] ?? ''));
        $ipv6Gateway = trim((string) ($data['ipv6_gateway'] ?? ''));
        $libvirtManaged = (int) (((string) ($data['libvirt_managed'] ?? '0')) === '1' ? 1 : 0);

        if ($name === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
            throw new RuntimeException('网络名称不合法。');
        }
        if ($bridge === '') {
            throw new RuntimeException('Bridge 名称不能为空。');
        }
        if (!preg_match('/^[a-zA-Z0-9._:-]+$/', $bridge)) {
            throw new RuntimeException('Bridge 名称不合法。');
        }
        if ($cidr !== '' && !preg_match('/^(\d+\.\d+\.\d+\.\d+)\/(\d{1,2})$/', $cidr, $matches)) {
            throw new RuntimeException('IPv4 子网格式不合法。');
        }
        if ($cidr !== '' && isset($matches[2]) && ((int) $matches[2] < 1 || (int) $matches[2] > 32)) {
            throw new RuntimeException('IPv4 子网前缀不合法。');
        }
        if ($gateway !== '' && !filter_var($gateway, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new RuntimeException('IPv4 网关地址不合法。');
        }
        foreach ([$dhcpStart, $dhcpEnd] as $ip) {
            if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                throw new RuntimeException('IPv4 DHCP 范围地址不合法。');
            }
        }
        if ($cidr !== '' && $dhcpStart !== '' && !$this->ipInSubnet($dhcpStart, explode('/', $cidr)[0], (int) explode('/', $cidr)[1])) {
            throw new RuntimeException('IPv4 DHCP 起始地址不在子网内。');
        }
        if ($cidr !== '' && $dhcpEnd !== '' && !$this->ipInSubnet($dhcpEnd, explode('/', $cidr)[0], (int) explode('/', $cidr)[1])) {
            throw new RuntimeException('IPv4 DHCP 结束地址不在子网内。');
        }
        if ($dhcpStart !== '' && $dhcpEnd !== '' && (int) ip2long($dhcpStart) > (int) ip2long($dhcpEnd)) {
            throw new RuntimeException('IPv4 DHCP 起始地址不能大于结束地址。');
        }
        if ($ipv6Cidr !== '' && !preg_match('/^([0-9a-fA-F:]+)\/(\d{1,3})$/', $ipv6Cidr, $matches6)) {
            throw new RuntimeException('IPv6 子网格式不合法。');
        }
        if ($ipv6Cidr !== '' && isset($matches6[2]) && ((int) $matches6[2] < 1 || (int) $matches6[2] > 128)) {
            throw new RuntimeException('IPv6 子网前缀不合法。');
        }
        if ($ipv6Gateway !== '' && !filter_var($ipv6Gateway, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new RuntimeException('IPv6 网关地址不合法。');
        }

        $exists = (new NetworkRepository())->findByName($name);
        if ($exists && $currentName !== $name) {
            throw new RuntimeException('网络名称已存在。');
        }

        return [
            'name' => $name,
            'cidr' => $cidr,
            'gateway' => $gateway !== '' ? $gateway : null,
            'bridge_name' => $bridge,
            'dhcp_start' => $dhcpStart !== '' ? $dhcpStart : null,
            'dhcp_end' => $dhcpEnd !== '' ? $dhcpEnd : null,
            'ipv6_cidr' => $ipv6Cidr !== '' ? $ipv6Cidr : null,
            'ipv6_gateway' => $ipv6Gateway !== '' ? $ipv6Gateway : null,
            'libvirt_managed' => $libvirtManaged,
            'autostart' => 1,
        ];
    }

    private function assertNetworkRenameAllowed(array $network): void
    {
        if ($this->isNetworkUsedByTemplates($network)) {
            throw new RuntimeException('该网络已被模板使用，暂不支持直接改名。');
        }
        if ($this->isNetworkUsedByVms($network)) {
            throw new RuntimeException('该网络已被虚拟机使用，暂不支持直接改名。');
        }
        $poolRepo = new IpPoolRepository();
        $networkId = (int) ($network['id'] ?? 0);
        if (($networkId > 0 && $poolRepo->findByNetworkId($networkId)) || $poolRepo->findByNetworkName((string) $network['name'])) {
            throw new RuntimeException('该网络已绑定地址池，暂不支持直接改名。');
        }
    }

    private function assertDangerousNetworkChangesAllowed(array $existing, array $payload): void
    {
        $inUse = $this->isNetworkUsedByTemplates($existing) || $this->isNetworkUsedByVms($existing);
        if (!$inUse) {
            return;
        }

        $lockedFields = [
            'bridge_name' => 'Bridge',
            'libvirt_managed' => '接入方式',
            'cidr' => 'IPv4 子网',
            'gateway' => 'IPv4 网关',
            'dhcp_start' => 'IPv4 DHCP 起始地址',
            'dhcp_end' => 'IPv4 DHCP 结束地址',
            'ipv6_cidr' => 'IPv6 子网',
            'ipv6_gateway' => 'IPv6 网关',
        ];

        foreach ($lockedFields as $field => $label) {
            if (($existing[$field] ?? null) !== ($payload[$field] ?? null)) {
                throw new RuntimeException('该网络已被模板或虚拟机引用，不能直接修改“' . $label . '”。请新建网络并迁移使用方。');
            }
        }
    }

    private function assertNetworkDeleteAllowed(array $network): void
    {
        if ($this->isNetworkUsedByTemplates($network)) {
            throw new RuntimeException('该网络仍被模板使用，不能删除。');
        }
        if ($this->isNetworkUsedByVms($network)) {
            throw new RuntimeException('该网络仍被虚拟机使用，不能删除。');
        }
    }

    private function isNetworkUsedByTemplates(array $network): bool
    {
        $nicService = new NicConfigService();
        foreach ((new TemplateRepository())->all() as $template) {
            foreach ($nicService->hydrateTemplateNics($template) as $nic) {
                if ($this->networkMatchesNic($network, $nic)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function isNetworkUsedByVms(array $network): bool
    {
        $nicService = new NicConfigService();
        foreach ((new VmRepository())->all() as $vm) {
            foreach ($nicService->hydrateVmNics($vm) as $nic) {
                if ($this->networkMatchesNic($network, $nic)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function networkMatchesNic(array $network, array $nic): bool
    {
        $networkId = (int) ($network['id'] ?? 0);
        if ($networkId > 0 && (int) ($nic['network_id'] ?? 0) === $networkId) {
            return true;
        }
        if ((string) ($nic['network_name'] ?? '') === (string) ($network['name'] ?? '')) {
            return true;
        }
        return trim((string) ($nic['bridge'] ?? '')) !== '' && trim((string) ($nic['bridge'] ?? '')) === trim((string) ($network['bridge_name'] ?? ''));
    }

    private function syncPoolNetworkName(int $networkId, string $networkName): void
    {
        $repo = new IpPoolRepository();
        foreach ($repo->findAllByNetworkId($networkId) as $pool) {
            $repo->update((int) $pool['id'], [
                'name' => (string) $pool['name'],
                'network_id' => (int) ($pool['network_id'] ?? $networkId),
                'network_name' => $networkName,
                'family' => (string) ($pool['family'] ?? 'ipv4'),
                'gateway' => $pool['gateway'] ?? null,
                'prefix_length' => $pool['prefix_length'] ?? null,
                'dns_servers' => $pool['dns_servers'] ?? null,
                'start_ip' => (string) $pool['start_ip'],
                'end_ip' => (string) $pool['end_ip'],
                'interface_name' => $pool['interface_name'] ?? null,
            ]);
        }
    }

    private function syncInlinePoolFamily(array $network, array $data, string $family): void
    {
        $prefix = strtolower($family) === 'ipv6' ? 'ipv6_' : 'ipv4_';
        $startIp = trim((string) ($data[$prefix . 'pool_start_ip'] ?? ''));
        $endIp = trim((string) ($data[$prefix . 'pool_end_ip'] ?? ''));
        $dnsServers = trim((string) ($data[$prefix . 'pool_dns_servers'] ?? config('cloud_init.dns')));

        $existing = $this->findExistingPoolForFamily($network, $family);
        if ($startIp === '' && $endIp === '') {
            if ($existing !== null) {
                (new IpPoolService())->delete((int) $existing['id']);
            }
            return;
        }

        if ($startIp === '' || $endIp === '') {
            throw new RuntimeException(strtoupper($family) . ' 地址池必须同时填写起始地址和结束地址，或留空表示关闭。');
        }

        $payload = [
            'name' => (string) $network['name'] . '-' . $family,
            'network_id' => (int) $network['id'],
            'network_name' => (string) $network['name'],
            'family' => $family,
            'start_ip' => $startIp,
            'end_ip' => $endIp,
            'dns_servers' => $dnsServers,
        ];

        $service = new IpPoolService();
        if ($existing !== null) {
            $service->update((int) $existing['id'], $payload);
            return;
        }
        $service->create($payload);
    }

    private function findExistingPoolForFamily(array $network, string $family): ?array
    {
        $repo = new IpPoolRepository();
        $networkId = (int) ($network['id'] ?? 0);
        if ($networkId > 0) {
            $pool = $repo->findByNetworkId($networkId, $family);
            if ($pool !== null) {
                return $pool;
            }
        }
        return $repo->findByNetworkName((string) ($network['name'] ?? ''), $family);
    }

    private function ipInSubnet(string $ip, string $network, int $prefix): bool
    {
        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);
        if ($ipLong === false || $networkLong === false) {
            return false;
        }
        $mask = -1 << (32 - $prefix);
        return ($ipLong & $mask) === ($networkLong & $mask);
    }

    private function ipv6InSubnet(string $ip, string $network, int $prefix): bool
    {
        $ipBin = inet_pton($ip);
        $networkBin = inet_pton($network);
        if ($ipBin === false || $networkBin === false) {
            return false;
        }
        $bytes = intdiv($prefix, 8);
        $bits = $prefix % 8;
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($networkBin, 0, $bytes)) {
            return false;
        }
        if ($bits === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $bits)) & 0xFF;
        return ((ord($ipBin[$bytes]) & $mask) === (ord($networkBin[$bytes]) & $mask));
    }

    private function compareIpv6(string $left, string $right): int
    {
        return strcmp((string) inet_pton($left), (string) inet_pton($right));
    }
}
