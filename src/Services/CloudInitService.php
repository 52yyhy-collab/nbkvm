<?php

declare(strict_types=1);
namespace Nbkvm\Services;
use RuntimeException;
class CloudInitService
{
    public function createSeedIso(string $vmName, string $username, ?string $password, ?string $sshKey): string
    {
        $vmDir = rtrim((string) config('vm_path'), '/') . '/' . $vmName;
        if (!is_dir($vmDir)) {
            mkdir($vmDir, 0755, true);
        }
        $userDataPath = $vmDir . '/user-data';
        $metaDataPath = $vmDir . '/meta-data';
        $isoPath = $vmDir . '/cloud-init.iso';
        $passwordBlock = $password ? "  passwd: '" . str_replace("'", "''", $password) . "'\n  lock_passwd: false\n  chpasswd: { expire: false }\n" : '';
        $sshBlock = $sshKey ? "  ssh_authorized_keys:\n    - " . trim($sshKey) . "\n" : '';
        $userData = "#cloud-config\nusers:\n  - name: {$username}\n    sudo: ALL=(ALL) NOPASSWD:ALL\n    shell: /bin/bash\n{$passwordBlock}{$sshBlock}package_update: true\n";
        $metaData = "instance-id: {$vmName}\nlocal-hostname: {$vmName}." . config('cloud_init.default_domain') . "\n";
        file_put_contents($userDataPath, $userData);
        file_put_contents($metaDataPath, $metaData);
        $cmd = sprintf('%s %s %s %s 2>&1', escapeshellcmd((string) config('cloud_init.cloud_localds')), escapeshellarg($isoPath), escapeshellarg($userDataPath), escapeshellarg($metaDataPath));
        exec($cmd, $output, $code);
        if ($code !== 0 || !is_file($isoPath)) {
            throw new RuntimeException('生成 cloud-init ISO 失败：' . implode("\n", $output));
        }
        @chmod($isoPath, 0644);
        return $isoPath;
    }
}
