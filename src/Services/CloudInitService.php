<?php

declare(strict_types=1);

namespace Nbkvm\Services;

use RuntimeException;

class CloudInitService
{
    public function createSeedIso(string $vmName, string $username, ?string $password, ?string $sshKey, array $networkConfigs = []): string
    {
        $vmDir = rtrim((string) config('vm_path'), '/') . '/' . $vmName;
        if (!is_dir($vmDir)) {
            mkdir($vmDir, 0755, true);
        }
        $userDataPath = $vmDir . '/user-data';
        $metaDataPath = $vmDir . '/meta-data';
        $networkPath = $vmDir . '/network-config';
        $isoPath = $vmDir . '/cloud-init.iso';

        $passwordBlock = $password ? "  passwd: '" . str_replace("'", "''", $password) . "'\n  lock_passwd: false\n  chpasswd: { expire: false }\n" : '';
        $sshBlock = $sshKey ? "  ssh_authorized_keys:\n    - " . trim($sshKey) . "\n" : '';
        $userData = "#cloud-config\nusers:\n  - name: {$username}\n    sudo: ALL=(ALL) NOPASSWD:ALL\n    shell: /bin/bash\n{$passwordBlock}{$sshBlock}package_update: true\n";
        $metaData = "instance-id: {$vmName}\nlocal-hostname: {$vmName}." . config('cloud_init.default_domain') . "\n";

        file_put_contents($userDataPath, $userData);
        file_put_contents($metaDataPath, $metaData);

        $cmd = sprintf(
            '%s %s %s %s',
            escapeshellcmd((string) config('cloud_init.cloud_localds')),
            escapeshellarg($isoPath),
            escapeshellarg($userDataPath),
            escapeshellarg($metaDataPath)
        );

        $networkYaml = $this->buildNetworkYaml($networkConfigs);
        if ($networkYaml !== null) {
            file_put_contents($networkPath, $networkYaml);
            $cmd .= ' -N ' . escapeshellarg($networkPath);
        }

        $cmd .= ' 2>&1';
        exec($cmd, $output, $code);
        if ($code !== 0 || !is_file($isoPath)) {
            throw new RuntimeException('生成 cloud-init ISO 失败：' . implode("\n", $output));
        }
        @chmod($isoPath, 0644);
        return $isoPath;
    }

    private function buildNetworkYaml(array $networkConfigs): ?string
    {
        if ($networkConfigs === []) {
            return null;
        }

        $lines = [
            'version: 2',
            'ethernets:',
        ];
        $added = false;

        foreach (array_values($networkConfigs) as $index => $config) {
            if (!is_array($config)) {
                continue;
            }
            $iface = trim((string) ($config['interface_name'] ?? ('eth' . $index))) ?: ('eth' . $index);
            $ipv4Mode = strtolower(trim((string) ($config['ipv4_mode'] ?? 'dhcp')));
            $ipv6Mode = strtolower(trim((string) ($config['ipv6_mode'] ?? 'none')));

            $lines[] = '  ' . $iface . ':';
            $lines[] = '    dhcp4: ' . ($ipv4Mode === 'dhcp' ? 'true' : 'false');
            $lines[] = '    dhcp6: false';
            $lines[] = '    accept-ra: ' . ($ipv6Mode === 'auto' ? 'true' : 'false');

            $addresses = [];
            if (in_array($ipv4Mode, ['static', 'pool'], true) && !empty($config['ipv4_address']) && !empty($config['ipv4_prefix_length'])) {
                $addresses[] = (string) $config['ipv4_address'] . '/' . (int) $config['ipv4_prefix_length'];
            }
            if (in_array($ipv6Mode, ['static', 'pool'], true) && !empty($config['ipv6_address']) && !empty($config['ipv6_prefix_length'])) {
                $addresses[] = (string) $config['ipv6_address'] . '/' . (int) $config['ipv6_prefix_length'];
            }
            if ($addresses !== []) {
                $lines[] = '    addresses:';
                foreach ($addresses as $address) {
                    $lines[] = '      - ' . $address;
                }
            }

            $routes = [];
            if (in_array($ipv4Mode, ['static', 'pool'], true) && !empty($config['ipv4_gateway'])) {
                $routes[] = [
                    'to' => 'default',
                    'via' => (string) $config['ipv4_gateway'],
                ];
            }
            if (in_array($ipv6Mode, ['static', 'pool'], true) && !empty($config['ipv6_gateway'])) {
                $routes[] = [
                    'to' => '::/0',
                    'via' => (string) $config['ipv6_gateway'],
                ];
            }
            if ($routes !== []) {
                $lines[] = '    routes:';
                foreach ($routes as $route) {
                    $lines[] = '      - to: ' . $route['to'];
                    $lines[] = '        via: ' . $route['via'];
                }
            }

            $dnsServers = [];
            foreach ([(string) ($config['ipv4_dns_servers'] ?? ''), (string) ($config['ipv6_dns_servers'] ?? '')] as $dnsRaw) {
                foreach (array_filter(array_map('trim', explode(',', $dnsRaw))) as $dns) {
                    $dnsServers[$dns] = true;
                }
            }
            if ($dnsServers !== []) {
                $lines[] = '    nameservers:';
                $lines[] = '      addresses:';
                foreach (array_keys($dnsServers) as $dns) {
                    $lines[] = '        - ' . $dns;
                }
            }

            $added = true;
        }

        return $added ? implode("\n", $lines) . "\n" : null;
    }
}
