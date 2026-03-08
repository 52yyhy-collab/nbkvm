<?php

declare(strict_types=1);

namespace Nbkvm\Services;

use Nbkvm\Repositories\IpAddressRepository;
use Nbkvm\Repositories\IpPoolRepository;
use Nbkvm\Repositories\VmRepository;
use RuntimeException;

class IpPoolService
{
    public function create(array $data): int
    {
        $payload = $this->normalizePayload($data);
        $poolId = (new IpPoolRepository())->create($payload + [
            'created_at' => date('c'),
        ]);
        $this->rebuildAddresses($poolId, $payload['start_ip'], $payload['end_ip'], $payload['family']);
        return $poolId;
    }

    public function update(int $id, array $data): void
    {
        $repo = new IpPoolRepository();
        $existing = $repo->find($id);
        if (!$existing) {
            throw new RuntimeException('IP 池不存在。');
        }
        if ($this->poolIsUsedByAnyVm($id)) {
            throw new RuntimeException('该 IP 池仍被虚拟机使用，不能修改范围。');
        }

        $payload = $this->normalizePayload($data, (string) $existing['name']);
        $repo->update($id, $payload);
        (new IpAddressRepository())->deleteByPool($id);
        $this->rebuildAddresses($id, $payload['start_ip'], $payload['end_ip'], $payload['family']);
    }

    public function delete(int $id): void
    {
        if ($this->poolIsUsedByAnyVm($id)) {
            throw new RuntimeException('该 IP 池仍被虚拟机使用，不能删除。');
        }
        (new IpAddressRepository())->deleteByPool($id);
        (new IpPoolRepository())->delete($id);
    }

    public function allocate(int $poolId, int $vmId): ?array
    {
        if ($poolId <= 0) {
            return null;
        }
        $pool = (new IpPoolRepository())->find($poolId);
        if (!$pool) {
            throw new RuntimeException('IP 池不存在。');
        }
        $free = (new IpAddressRepository())->findFree($poolId);
        if (!$free) {
            throw new RuntimeException('IP 池里没有可用地址。');
        }

        $network = (new NetworkService())->findForPool(
            ($pool['network_id'] ?? null) !== null ? (int) $pool['network_id'] : null,
            (string) ($pool['network_name'] ?? '')
        );
        (new IpAddressRepository())->assign((int) $free['id'], $vmId);

        $family = strtolower((string) ($pool['family'] ?? 'ipv4'));
        return [
            'ip_address' => (string) $free['ip_address'],
            'gateway' => (new NetworkService())->gatewayForFamily($network, $family),
            'prefix_length' => (new NetworkService())->prefixForFamily($network, $family),
            'dns_servers' => (string) ($pool['dns_servers'] ?? ''),
            'family' => $family,
            'pool' => $pool,
            'network' => $network,
        ];
    }

    public function releaseByVmId(int $vmId): void
    {
        (new IpAddressRepository())->releaseByVmId($vmId);
    }

    private function normalizePayload(array $data, ?string $currentName = null): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $family = strtolower(trim((string) ($data['family'] ?? 'ipv4')));
        $startIp = trim((string) ($data['start_ip'] ?? ''));
        $endIp = trim((string) ($data['end_ip'] ?? ''));
        $dns = trim((string) ($data['dns_servers'] ?? config('cloud_init.dns')));
        $networkId = ($data['network_id'] ?? null) !== null && trim((string) $data['network_id']) !== '' ? (int) $data['network_id'] : null;
        $networkName = trim((string) ($data['network_name'] ?? ''));

        if ($name === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
            throw new RuntimeException('IP 池名称不合法。');
        }
        if (!in_array($family, ['ipv4', 'ipv6'], true)) {
            throw new RuntimeException('IP 池地址族只支持 ipv4 或 ipv6。');
        }

        $exists = (new IpPoolRepository())->findByName($name);
        if ($exists && $currentName !== $name) {
            throw new RuntimeException('IP 池名称已存在。');
        }

        $network = (new NetworkService())->findForPool($networkId, $networkName);
        if ($startIp === '' || $endIp === '') {
            throw new RuntimeException('IP 池起始地址和结束地址不能为空。');
        }
        (new NetworkService())->assertPoolMatchesNetwork($network, $startIp, $endIp, $family);

        return [
            'name' => $name,
            'network_id' => (int) $network['id'],
            'network_name' => (string) $network['name'],
            'family' => $family,
            'gateway' => (new NetworkService())->gatewayForFamily($network, $family),
            'prefix_length' => (new NetworkService())->prefixForFamily($network, $family),
            'dns_servers' => $dns !== '' ? $dns : null,
            'start_ip' => $startIp,
            'end_ip' => $endIp,
            'interface_name' => null,
        ];
    }

    private function rebuildAddresses(int $poolId, string $startIp, string $endIp, string $family): void
    {
        $ipRepo = new IpAddressRepository();
        if ($family === 'ipv4') {
            $startLong = (int) ip2long($startIp);
            $endLong = (int) ip2long($endIp);
            for ($i = $startLong; $i <= $endLong; $i++) {
                $ipRepo->create([
                    'pool_id' => $poolId,
                    'ip_address' => long2ip($i),
                    'status' => 'free',
                    'vm_id' => null,
                    'created_at' => date('c'),
                    'updated_at' => date('c'),
                ]);
            }
            return;
        }

        $current = $startIp;
        while (true) {
            $ipRepo->create([
                'pool_id' => $poolId,
                'ip_address' => $current,
                'status' => 'free',
                'vm_id' => null,
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ]);
            if ($current === $endIp) {
                break;
            }
            $current = $this->incrementIpv6($current);
            if ($this->compareIpv6($current, $endIp) > 0) {
                break;
            }
        }
    }

    private function poolIsUsedByAnyVm(int $poolId): bool
    {
        $nicService = new NicConfigService();
        foreach ((new VmRepository())->all() as $vm) {
            foreach ($nicService->hydrateVmNics($vm) as $nic) {
                if ((int) ($nic['ipv4_pool_id'] ?? 0) === $poolId || (int) ($nic['ipv6_pool_id'] ?? 0) === $poolId) {
                    return true;
                }
            }
        }
        return false;
    }

    private function compareIpv6(string $left, string $right): int
    {
        return strcmp((string) inet_pton($left), (string) inet_pton($right));
    }

    private function incrementIpv6(string $ip): string
    {
        $bytes = inet_pton($ip);
        if ($bytes === false) {
            throw new RuntimeException('非法 IPv6 地址。');
        }
        $octets = array_values(unpack('C*', $bytes));
        for ($i = count($octets) - 1; $i >= 0; $i--) {
            if ($octets[$i] < 255) {
                $octets[$i]++;
                break;
            }
            $octets[$i] = 0;
        }
        return inet_ntop(pack('C*', ...$octets)) ?: $ip;
    }
}
