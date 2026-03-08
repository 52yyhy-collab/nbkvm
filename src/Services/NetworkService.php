<?php

declare(strict_types=1);
namespace Nbkvm\Services;
use Nbkvm\Repositories\NetworkRepository;
use RuntimeException;
class NetworkService
{
    public function create(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $cidr = trim((string) ($data['cidr'] ?? ''));
        $gateway = trim((string) ($data['gateway'] ?? ''));
        $bridge = trim((string) ($data['bridge_name'] ?? '')) ?: null;
        $dhcpStart = trim((string) ($data['dhcp_start'] ?? '')) ?: null;
        $dhcpEnd = trim((string) ($data['dhcp_end'] ?? '')) ?: null;
        if ($name === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
            throw new RuntimeException('网络名称不合法。');
        }
        if (!preg_match('/^(\d+\.\d+\.\d+\.\d+)\/(\d{1,2})$/', $cidr, $m)) {
            throw new RuntimeException('CIDR 格式不合法。');
        }
        $prefix = (int) $m[2];
        if ($prefix < 1 || $prefix > 32) {
            throw new RuntimeException('CIDR 前缀不合法。');
        }
        if (!filter_var($gateway, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new RuntimeException('网关地址不合法。');
        }
        foreach ([$dhcpStart, $dhcpEnd] as $ip) {
            if ($ip !== null && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                throw new RuntimeException('DHCP 范围地址不合法。');
            }
        }
        return (new NetworkRepository())->create([
            'name' => $name,
            'cidr' => $cidr,
            'gateway' => $gateway,
            'bridge_name' => $bridge,
            'dhcp_start' => $dhcpStart,
            'dhcp_end' => $dhcpEnd,
            'libvirt_managed' => 1,
            'autostart' => 1,
            'created_at' => date('c'),
        ]);
    }
    public function assertPoolMatchesNetwork(string $networkName, string $gateway, int $prefix, string $startIp, string $endIp): void
    {
        $network = (new NetworkRepository())->findByName($networkName);
        if (!$network) {
            return;
        }
        if ((string) $network['gateway'] !== $gateway) {
            throw new RuntimeException('IP 池网关与所选网络不匹配。');
        }
        if (!preg_match('/^(\d+\.\d+\.\d+\.\d+)\/(\d{1,2})$/', (string) $network['cidr'], $m)) {
            return;
        }
        $networkBase = $m[1];
        $networkPrefix = (int) $m[2];
        if ($networkPrefix !== $prefix) {
            throw new RuntimeException('IP 池前缀长度与网络不匹配。');
        }
        foreach ([$startIp, $endIp, $gateway] as $ip) {
            if (!$this->ipInSubnet($ip, $networkBase, $networkPrefix)) {
                throw new RuntimeException('IP 池地址范围不在所选网络内。');
            }
        }
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
}
