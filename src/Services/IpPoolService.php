<?php

declare(strict_types=1);
namespace Nbkvm\Services;
use Nbkvm\Repositories\IpAddressRepository;
use Nbkvm\Repositories\IpPoolRepository;
use RuntimeException;
class IpPoolService
{
    public function create(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $networkName = trim((string) ($data['network_name'] ?? 'default'));
        $gateway = trim((string) ($data['gateway'] ?? ''));
        $prefix = (int) ($data['prefix_length'] ?? 24);
        $startIp = trim((string) ($data['start_ip'] ?? ''));
        $endIp = trim((string) ($data['end_ip'] ?? ''));
        $dns = trim((string) ($data['dns_servers'] ?? '1.1.1.1,8.8.8.8'));
        $interfaceName = trim((string) ($data['interface_name'] ?? 'eth0'));
        foreach ([$gateway, $startIp, $endIp] as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                throw new RuntimeException('IP 池参数里存在非法 IPv4 地址。');
            }
        }
        $startLong = ip2long($startIp);
        $endLong = ip2long($endIp);
        if ($startLong === false || $endLong === false || $startLong > $endLong) {
            throw new RuntimeException('IP 范围不合法。');
        }
        $poolId = (new IpPoolRepository())->create([
            'name' => $name,
            'network_name' => $networkName,
            'gateway' => $gateway,
            'prefix_length' => $prefix,
            'dns_servers' => $dns,
            'start_ip' => $startIp,
            'end_ip' => $endIp,
            'interface_name' => $interfaceName,
            'created_at' => date('c'),
        ]);
        $ipRepo = new IpAddressRepository();
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
        return $poolId;
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
        (new IpAddressRepository())->assign((int) $free['id'], $vmId);
        return [
            'ip_address' => (string) $free['ip_address'],
            'gateway' => (string) $pool['gateway'],
            'prefix_length' => (int) $pool['prefix_length'],
            'dns_servers' => (string) ($pool['dns_servers'] ?? ''),
            'interface_name' => (string) ($pool['interface_name'] ?? 'eth0'),
            'pool' => $pool,
        ];
    }
    public function releaseByVmId(int $vmId): void
    {
        (new IpAddressRepository())->releaseByVmId($vmId);
    }
}
