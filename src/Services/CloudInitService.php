<?php

declare(strict_types=1);

namespace Nbkvm\Services;

use RuntimeException;

class CloudInitService
{
    /**
     * 兼容旧调用签名。
     */
    public function createSeedIso(
        string $vmName,
        string $username,
        ?string $password,
        ?string $sshKey,
        array $networkConfigs = [],
        array $advanced = []
    ): string {
        $config = [
            'username' => $username,
            'password' => $password,
            'ssh_key' => $sshKey,
        ] + $advanced;

        return $this->createSeedIsoFromConfig($vmName, $config, $networkConfigs);
    }

    public function createSeedIsoFromConfig(string $vmName, array $config, array $networkConfigs = []): string
    {
        $vmDir = rtrim((string) config('vm_path'), '/') . '/' . $vmName;
        if (!is_dir($vmDir)) {
            mkdir($vmDir, 0755, true);
        }

        $userDataPath = $vmDir . '/user-data';
        $metaDataPath = $vmDir . '/meta-data';
        $networkPath = $vmDir . '/network-config';
        $isoPath = $vmDir . '/cloud-init.iso';

        $userData = $this->buildUserData($vmName, $config);
        $metaData = $this->buildMetaData($vmName, $config);

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

    private function buildUserData(string $vmName, array $config): string
    {
        $username = trim((string) ($config['username'] ?? 'ubuntu')) ?: 'ubuntu';
        $password = (string) ($config['password'] ?? '');
        $password = trim($password);
        $passwordIsHashed = (bool) ($config['password_is_hashed'] ?? false);

        if (!$passwordIsHashed && $password !== '' && preg_match('/^\$[0-9a-zA-Z]+\$.+$/', $password)) {
            $passwordIsHashed = true;
        }

        $sshKeys = $this->parseSshKeys($config['ssh_key'] ?? null);
        $hostname = trim((string) ($config['hostname'] ?? $vmName));
        $dnsServers = $this->csvList((string) ($config['dns_servers'] ?? ''));
        $searchDomains = $this->csvList((string) ($config['search_domain'] ?? ''));
        $extraUserData = trim((string) ($config['extra_user_data'] ?? ''));

        $lines = [
            '#cloud-config',
            'users:',
            '  - name: ' . $this->yamlScalar($username),
            '    sudo: ALL=(ALL) NOPASSWD:ALL',
            '    shell: /bin/bash',
        ];

        if ($password !== '') {
            if ($passwordIsHashed) {
                $lines[] = '    passwd: ' . $this->yamlScalar($password);
            } else {
                $lines[] = '    plain_text_passwd: ' . $this->yamlScalar($password);
            }
            $lines[] = '    lock_passwd: false';
        } else {
            $lines[] = '    lock_passwd: true';
        }

        if ($sshKeys !== []) {
            $lines[] = '    ssh_authorized_keys:';
            foreach ($sshKeys as $key) {
                $lines[] = '      - ' . $this->yamlScalar($key);
            }
        }

        if ($password !== '') {
            $lines[] = 'chpasswd:';
            $lines[] = '  expire: false';
            $lines[] = 'ssh_pwauth: true';
        }

        if ($hostname !== '') {
            $lines[] = 'hostname: ' . $this->yamlScalar($hostname);
            $lines[] = 'preserve_hostname: false';
        }

        if ($dnsServers !== [] || $searchDomains !== []) {
            $lines[] = 'manage_resolv_conf: true';
            $lines[] = 'resolv_conf:';
            if ($dnsServers !== []) {
                $lines[] = '  nameservers:';
                foreach ($dnsServers as $dns) {
                    $lines[] = '    - ' . $this->yamlScalar($dns);
                }
            }
            if ($searchDomains !== []) {
                $lines[] = '  searchdomains:';
                foreach ($searchDomains as $domain) {
                    $lines[] = '    - ' . $this->yamlScalar($domain);
                }
            }
        }

        $lines[] = 'package_update: true';

        $payload = implode("\n", $lines) . "\n";
        if ($extraUserData !== '') {
            $extraUserData = preg_replace('/^\s*#cloud-config\s*/', '', $extraUserData) ?? $extraUserData;
            $payload .= "\n# nbkvm-extra-user-data\n" . rtrim($extraUserData) . "\n";
        }

        return $payload;
    }

    private function buildMetaData(string $vmName, array $config): string
    {
        $hostname = trim((string) ($config['hostname'] ?? $vmName));
        if ($hostname === '') {
            $hostname = $vmName;
        }

        if (!str_contains($hostname, '.')) {
            $hostname .= '.' . (string) config('cloud_init.default_domain');
        }

        return 'instance-id: ' . $this->yamlScalar($vmName) . "\n"
            . 'local-hostname: ' . $this->yamlScalar($hostname) . "\n";
    }

    private function parseSshKeys(mixed $raw): array
    {
        if (is_array($raw)) {
            $keys = $raw;
        } else {
            $keys = preg_split('/\r?\n/', (string) $raw) ?: [];
        }

        $result = [];
        foreach ($keys as $key) {
            $text = trim((string) $key);
            if ($text === '') {
                continue;
            }
            $result[] = $text;
        }

        return array_values(array_unique($result));
    }

    private function csvList(string $raw): array
    {
        $items = [];
        foreach (preg_split('/[\n,]/', $raw) ?: [] as $piece) {
            $value = trim($piece);
            if ($value === '') {
                continue;
            }
            $items[] = $value;
        }
        return array_values(array_unique($items));
    }

    private function yamlScalar(string $value): string
    {
        if ($value === '') {
            return "''";
        }

        $looksLikeYamlNative = preg_match('/^(?:~|null|true|false|yes|no|on|off|[-+]?[0-9]+(?:\.[0-9]+)?)$/i', $value) === 1;
        if (!$looksLikeYamlNative && preg_match('/^[a-zA-Z0-9._@\/-]+$/', $value)) {
            return $value;
        }

        return "'" . str_replace("'", "''", $value) . "'";
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
