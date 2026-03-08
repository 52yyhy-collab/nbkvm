<?php

declare(strict_types=1);

namespace Nbkvm\Services;

use Nbkvm\Support\Database;
use PDO;

class SchemaService
{
    private ?PDO $connection = null;

    public function __construct(private readonly ?PDO $pdo = null)
    {
    }

    public function ensure(): void
    {
        $pdo = $this->db();
        $driver = (string) config('database.driver', 'sqlite');
        $idColumn = $driver === 'mysql' ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $boolType = $driver === 'mysql' ? 'TINYINT(1)' : 'INTEGER';

        $queries = [
            "CREATE TABLE IF NOT EXISTS users (id {$idColumn}, username VARCHAR(191) NOT NULL UNIQUE, password_hash TEXT NOT NULL, role VARCHAR(50) NOT NULL DEFAULT 'admin', created_at TEXT NOT NULL)",
            "CREATE TABLE IF NOT EXISTS settings (id {$idColumn}, key_name VARCHAR(191) NOT NULL UNIQUE, value_text TEXT DEFAULT NULL, updated_at TEXT DEFAULT NULL)",
            "CREATE TABLE IF NOT EXISTS images (id {$idColumn}, name VARCHAR(191) NOT NULL, original_name VARCHAR(191) NOT NULL, path TEXT NOT NULL, extension VARCHAR(32) NOT NULL, size_bytes BIGINT NOT NULL DEFAULT 0, created_at TEXT NOT NULL)",
            "CREATE TABLE IF NOT EXISTS networks (id {$idColumn}, name VARCHAR(191) NOT NULL UNIQUE, cidr VARCHAR(64) NOT NULL, gateway VARCHAR(128) DEFAULT NULL, bridge_name VARCHAR(64) DEFAULT NULL, dhcp_start VARCHAR(128) DEFAULT NULL, dhcp_end VARCHAR(128) DEFAULT NULL, ipv6_cidr VARCHAR(128) DEFAULT NULL, ipv6_gateway VARCHAR(128) DEFAULT NULL, libvirt_managed {$boolType} NOT NULL DEFAULT 0, autostart {$boolType} NOT NULL DEFAULT 1, created_at TEXT NOT NULL)",
            "CREATE TABLE IF NOT EXISTS ip_pools (id {$idColumn}, name VARCHAR(191) NOT NULL UNIQUE, network_id BIGINT DEFAULT NULL, network_name VARCHAR(191) NOT NULL DEFAULT '', family VARCHAR(16) NOT NULL DEFAULT 'ipv4', gateway VARCHAR(128) DEFAULT NULL, prefix_length INTEGER DEFAULT NULL, dns_servers TEXT DEFAULT NULL, start_ip VARCHAR(128) NOT NULL, end_ip VARCHAR(128) NOT NULL, interface_name VARCHAR(64) DEFAULT NULL, created_at TEXT NOT NULL)",
            "CREATE TABLE IF NOT EXISTS ip_pool_addresses (id {$idColumn}, pool_id BIGINT NOT NULL, ip_address VARCHAR(128) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'free', vm_id BIGINT DEFAULT NULL, created_at TEXT NOT NULL, updated_at TEXT DEFAULT NULL)",
            "CREATE TABLE IF NOT EXISTS templates (id {$idColumn}, name VARCHAR(191) NOT NULL UNIQUE, image_id BIGINT NOT NULL, image_type VARCHAR(32) NOT NULL, os_variant VARCHAR(191) NOT NULL, cpu INTEGER NOT NULL DEFAULT 1, memory_mb INTEGER NOT NULL DEFAULT 512, disk_size_gb INTEGER NOT NULL DEFAULT 10, disk_bus VARCHAR(50) NOT NULL DEFAULT 'virtio', network_name VARCHAR(191) NOT NULL DEFAULT 'default', notes TEXT DEFAULT '', cloud_init_enabled {$boolType} NOT NULL DEFAULT 0, cloud_init_user VARCHAR(191) DEFAULT NULL, cloud_init_password VARCHAR(191) DEFAULT NULL, cloud_init_ssh_key TEXT DEFAULT NULL, virtualization_mode VARCHAR(32) DEFAULT 'kvm', cpu_sockets INTEGER DEFAULT 1, cpu_cores INTEGER DEFAULT 1, cpu_threads INTEGER DEFAULT 1, machine_type VARCHAR(64) DEFAULT 'pc', firmware_type VARCHAR(32) DEFAULT 'bios', gpu_type VARCHAR(64) DEFAULT 'cirrus', autostart_default {$boolType} NOT NULL DEFAULT 0, memory_max_mb INTEGER DEFAULT NULL, memory_overcommit_percent INTEGER DEFAULT 100, disk_overcommit_enabled {$boolType} NOT NULL DEFAULT 0, disks_json TEXT DEFAULT NULL, nics_json TEXT DEFAULT NULL, created_at TEXT NOT NULL)",
            "CREATE TABLE IF NOT EXISTS vms (id {$idColumn}, name VARCHAR(191) NOT NULL UNIQUE, template_id BIGINT NOT NULL, cpu INTEGER NOT NULL, cpu_sockets INTEGER DEFAULT 1, cpu_cores INTEGER DEFAULT 1, cpu_threads INTEGER DEFAULT 1, memory_mb INTEGER NOT NULL, disk_path TEXT NOT NULL, disk_size_gb INTEGER NOT NULL, disks_json TEXT DEFAULT NULL, network_name VARCHAR(191) NOT NULL DEFAULT 'default', ip_pool_id BIGINT DEFAULT NULL, status VARCHAR(50) NOT NULL, ip_address VARCHAR(191) DEFAULT NULL, xml_path TEXT NOT NULL, cloud_init_iso_path TEXT DEFAULT NULL, vnc_display VARCHAR(191) DEFAULT NULL, expires_at TEXT DEFAULT NULL, expire_action VARCHAR(32) DEFAULT 'pause', expire_grace_days INTEGER DEFAULT 3, expired_at TEXT DEFAULT NULL, nics_json TEXT DEFAULT NULL, created_at TEXT NOT NULL, updated_at TEXT DEFAULT NULL)",
            "CREATE TABLE IF NOT EXISTS snapshots (id {$idColumn}, vm_id BIGINT NOT NULL, name VARCHAR(191) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'created', created_at TEXT NOT NULL)",
            "CREATE TABLE IF NOT EXISTS audit_logs (id {$idColumn}, username VARCHAR(191) DEFAULT NULL, action VARCHAR(191) NOT NULL, target_type VARCHAR(100) DEFAULT NULL, target_name VARCHAR(191) DEFAULT NULL, detail TEXT DEFAULT NULL, created_at TEXT NOT NULL)",
            "CREATE TABLE IF NOT EXISTS jobs (id {$idColumn}, name VARCHAR(191) NOT NULL, target_type VARCHAR(100) DEFAULT NULL, target_name VARCHAR(191) DEFAULT NULL, status VARCHAR(50) NOT NULL, output TEXT DEFAULT NULL, created_at TEXT NOT NULL, updated_at TEXT DEFAULT NULL)",
        ];

        foreach ($queries as $query) {
            $pdo->exec($query);
        }

        $ensureColumns = [
            ['networks', 'ipv6_cidr', 'VARCHAR(128) DEFAULT NULL'],
            ['networks', 'ipv6_gateway', 'VARCHAR(128) DEFAULT NULL'],
            ['networks', 'libvirt_managed', $boolType . ' NOT NULL DEFAULT 0'],
            ['networks', 'autostart', $boolType . ' NOT NULL DEFAULT 1'],
            ['ip_pools', 'network_id', 'BIGINT DEFAULT NULL'],
            ['ip_pools', 'family', "VARCHAR(16) NOT NULL DEFAULT 'ipv4'"],
            ['templates', 'virtualization_mode', "VARCHAR(32) DEFAULT 'kvm'"],
            ['templates', 'cpu_sockets', 'INTEGER DEFAULT 1'],
            ['templates', 'cpu_cores', 'INTEGER DEFAULT 1'],
            ['templates', 'cpu_threads', 'INTEGER DEFAULT 1'],
            ['templates', 'machine_type', "VARCHAR(64) DEFAULT 'pc'"],
            ['templates', 'firmware_type', "VARCHAR(32) DEFAULT 'bios'"],
            ['templates', 'gpu_type', "VARCHAR(64) DEFAULT 'cirrus'"],
            ['templates', 'autostart_default', $boolType . ' NOT NULL DEFAULT 0'],
            ['templates', 'memory_max_mb', 'INTEGER DEFAULT NULL'],
            ['templates', 'memory_overcommit_percent', 'INTEGER DEFAULT 100'],
            ['templates', 'disk_overcommit_enabled', $boolType . ' NOT NULL DEFAULT 0'],
            ['templates', 'disks_json', 'TEXT DEFAULT NULL'],
            ['templates', 'nics_json', 'TEXT DEFAULT NULL'],
            ['vms', 'cpu_sockets', 'INTEGER DEFAULT 1'],
            ['vms', 'cpu_cores', 'INTEGER DEFAULT 1'],
            ['vms', 'cpu_threads', 'INTEGER DEFAULT 1'],
            ['vms', 'disks_json', 'TEXT DEFAULT NULL'],
            ['vms', 'ip_pool_id', 'BIGINT DEFAULT NULL'],
            ['vms', 'expires_at', 'TEXT DEFAULT NULL'],
            ['vms', 'expire_action', "VARCHAR(32) DEFAULT 'pause'"],
            ['vms', 'expire_grace_days', 'INTEGER DEFAULT 3'],
            ['vms', 'expired_at', 'TEXT DEFAULT NULL'],
            ['vms', 'nics_json', 'TEXT DEFAULT NULL'],
        ];

        foreach ($ensureColumns as [$table, $column, $definition]) {
            $this->ensureColumn((string) $table, (string) $column, (string) $definition);
        }

        $this->seedDefaultAdmin();
        $this->seedDefaultSettings();
        $this->seedDefaultNetwork();
        $this->backfillPoolNetworkLinks();
        $this->backfillPoolSubnetDetails();
        $this->migrateTemplateNicJson();
        $this->migrateVmNicJson();
        $this->migrateVmDiskJson();
        $this->migrateVmCpuTopology();
    }

    private function db(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }
        $this->connection = $this->pdo ?? (new Database())->pdo();
        return $this->connection;
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        try {
            $this->db()->query("SELECT {$column} FROM {$table} LIMIT 1");
        } catch (\Throwable) {
            $this->db()->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    private function seedDefaultAdmin(): void
    {
        $username = (string) config('auth.default_username');
        $exists = $this->db()->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
        $exists->execute(['username' => $username]);
        if ((int) $exists->fetchColumn() > 0) {
            return;
        }

        $stmt = $this->db()->prepare('INSERT INTO users (username, password_hash, role, created_at) VALUES (:username, :password_hash, :role, :created_at)');
        $stmt->execute([
            'username' => $username,
            'password_hash' => password_hash((string) config('auth.default_password'), PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => date('c'),
        ]);
    }

    private function seedDefaultSettings(): void
    {
        $settings = [
            'upload_max_size_mb' => (string) config('defaults.upload_max_size_mb'),
            'default_expire_action' => (string) config('defaults.expire_action'),
            'expire_grace_days' => (string) config('defaults.expire_grace_days'),
            'system_variables_json' => '{}',
        ];

        foreach ($settings as $key => $value) {
            $stmt = $this->db()->prepare('SELECT COUNT(*) FROM settings WHERE key_name = :key_name');
            $stmt->execute(['key_name' => $key]);
            if ((int) $stmt->fetchColumn() > 0) {
                continue;
            }
            $insert = $this->db()->prepare('INSERT INTO settings (key_name, value_text, updated_at) VALUES (:key_name, :value_text, :updated_at)');
            $insert->execute([
                'key_name' => $key,
                'value_text' => $value,
                'updated_at' => date('c'),
            ]);
        }
    }

    private function seedDefaultNetwork(): void
    {
        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM networks WHERE name = :name');
        $stmt->execute(['name' => 'default']);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $insert = $this->db()->prepare('INSERT INTO networks (name, cidr, gateway, bridge_name, dhcp_start, dhcp_end, ipv6_cidr, ipv6_gateway, libvirt_managed, autostart, created_at) VALUES (:name, :cidr, :gateway, :bridge_name, :dhcp_start, :dhcp_end, :ipv6_cidr, :ipv6_gateway, :libvirt_managed, :autostart, :created_at)');
        $insert->execute([
            'name' => 'default',
            'cidr' => '192.168.122.0/24',
            'gateway' => '192.168.122.1',
            'bridge_name' => 'virbr0',
            'dhcp_start' => '192.168.122.2',
            'dhcp_end' => '192.168.122.254',
            'ipv6_cidr' => null,
            'ipv6_gateway' => null,
            'libvirt_managed' => 1,
            'autostart' => 1,
            'created_at' => date('c'),
        ]);
    }

    private function backfillPoolNetworkLinks(): void
    {
        $networks = $this->db()->query('SELECT id, name FROM networks')->fetchAll();
        $networkIds = [];
        foreach ($networks as $network) {
            $networkIds[(string) $network['name']] = (int) $network['id'];
        }

        $rows = $this->db()->query('SELECT id, network_id, network_name FROM ip_pools')->fetchAll();
        $update = $this->db()->prepare('UPDATE ip_pools SET network_id = :network_id, network_name = :network_name WHERE id = :id');
        foreach ($rows as $row) {
            $networkName = trim((string) ($row['network_name'] ?? ''));
            $networkId = (int) ($row['network_id'] ?? 0);
            if ($networkId <= 0 && $networkName !== '' && isset($networkIds[$networkName])) {
                $update->execute([
                    'network_id' => $networkIds[$networkName],
                    'network_name' => $networkName,
                    'id' => (int) $row['id'],
                ]);
                continue;
            }
            if ($networkId > 0 && $networkName === '') {
                $name = array_search($networkId, $networkIds, true);
                if (is_string($name) && $name !== '') {
                    $update->execute([
                        'network_id' => $networkId,
                        'network_name' => $name,
                        'id' => (int) $row['id'],
                    ]);
                }
            }
        }
    }

    private function backfillPoolSubnetDetails(): void
    {
        $rows = $this->db()->query('SELECT p.id, p.family, p.network_id, p.network_name, p.gateway, p.prefix_length FROM ip_pools p ORDER BY p.id ASC')->fetchAll();
        $networkRepo = new \Nbkvm\Repositories\NetworkRepository($this->db());
        $update = $this->db()->prepare('UPDATE ip_pools SET gateway = :gateway, prefix_length = :prefix_length, network_id = :network_id, network_name = :network_name WHERE id = :id');

        foreach ($rows as $row) {
            $network = null;
            if ((int) ($row['network_id'] ?? 0) > 0) {
                $network = $networkRepo->findById((int) $row['network_id']);
            }
            if ($network === null && trim((string) ($row['network_name'] ?? '')) !== '') {
                $network = $networkRepo->findByName((string) $row['network_name']);
            }
            if ($network === null) {
                continue;
            }

            $family = strtolower((string) ($row['family'] ?? 'ipv4'));
            $gateway = $family === 'ipv6'
                ? ((string) ($network['ipv6_gateway'] ?? '') ?: null)
                : ((string) ($network['gateway'] ?? '') ?: null);
            $cidr = $family === 'ipv6'
                ? (string) ($network['ipv6_cidr'] ?? '')
                : (string) ($network['cidr'] ?? '');
            $prefix = $this->extractPrefix($cidr);

            $update->execute([
                'gateway' => $gateway,
                'prefix_length' => $prefix,
                'network_id' => (int) $network['id'],
                'network_name' => (string) $network['name'],
                'id' => (int) $row['id'],
            ]);
        }
    }

    private function migrateTemplateNicJson(): void
    {
        $service = new NicConfigService($this->db());
        $rows = $this->db()->query('SELECT id, network_name, nics_json FROM templates ORDER BY id ASC')->fetchAll();
        $update = $this->db()->prepare('UPDATE templates SET network_name = :network_name, nics_json = :nics_json WHERE id = :id');

        foreach ($rows as $row) {
            $nics = $service->hydrateTemplateNics($row);
            $networkName = $service->primaryNetworkName($nics);
            $json = json_encode($nics, JSON_UNESCAPED_UNICODE);
            if (!is_string($json)) {
                continue;
            }
            if ((string) ($row['network_name'] ?? '') === $networkName && trim((string) ($row['nics_json'] ?? '')) === $json) {
                continue;
            }
            $update->execute([
                'network_name' => $networkName,
                'nics_json' => $json,
                'id' => (int) $row['id'],
            ]);
        }
    }

    private function migrateVmNicJson(): void
    {
        $service = new NicConfigService($this->db());
        $rows = $this->db()->query('SELECT id, network_name, ip_pool_id, ip_address, nics_json FROM vms ORDER BY id ASC')->fetchAll();
        $update = $this->db()->prepare('UPDATE vms SET network_name = :network_name, ip_pool_id = :ip_pool_id, nics_json = :nics_json WHERE id = :id');

        foreach ($rows as $row) {
            $nics = $service->hydrateVmNics($row);
            $networkName = $service->primaryNetworkName($nics);
            $poolId = $service->primaryPoolId($nics);
            $json = json_encode($nics, JSON_UNESCAPED_UNICODE);
            if (!is_string($json)) {
                continue;
            }
            $currentPoolId = ($row['ip_pool_id'] ?? null) !== null ? (int) $row['ip_pool_id'] : null;
            if ((string) ($row['network_name'] ?? '') === $networkName && $currentPoolId === $poolId && trim((string) ($row['nics_json'] ?? '')) === $json) {
                continue;
            }
            $update->execute([
                'network_name' => $networkName,
                'ip_pool_id' => $poolId,
                'nics_json' => $json,
                'id' => (int) $row['id'],
            ]);
        }
    }

    private function migrateVmDiskJson(): void
    {
        $service = new DiskConfigService();
        $rows = $this->db()->query('SELECT id, disk_path, disk_size_gb, disks_json FROM vms ORDER BY id ASC')->fetchAll();
        $update = $this->db()->prepare('UPDATE vms SET disks_json = :disks_json WHERE id = :id');

        foreach ($rows as $row) {
            $defaultSize = max(5, (int) ($row['disk_size_gb'] ?? 20));
            $defaultBus = (string) config('libvirt.default_disk_bus');
            $json = trim((string) ($row['disks_json'] ?? ''));
            if ($json === '') {
                $disks = [[
                    'name' => 'disk0',
                    'path' => (string) ($row['disk_path'] ?? ''),
                    'size_gb' => $defaultSize,
                    'bus' => $defaultBus,
                    'format' => 'qcow2',
                    'is_primary' => true,
                ]];
            } else {
                $disks = $service->normalizeJson($json, $defaultSize, $defaultBus, false);
            }

            $encoded = json_encode($disks, JSON_UNESCAPED_UNICODE);
            if (!is_string($encoded) || $encoded === $json) {
                continue;
            }
            $update->execute([
                'disks_json' => $encoded,
                'id' => (int) $row['id'],
            ]);
        }
    }

    private function migrateVmCpuTopology(): void
    {
        $rows = $this->db()->query('SELECT id, cpu, cpu_sockets, cpu_cores, cpu_threads FROM vms ORDER BY id ASC')->fetchAll();
        $update = $this->db()->prepare('UPDATE vms SET cpu_sockets = :cpu_sockets, cpu_cores = :cpu_cores, cpu_threads = :cpu_threads WHERE id = :id');

        foreach ($rows as $row) {
            $sockets = (int) ($row['cpu_sockets'] ?? 0);
            $cores = (int) ($row['cpu_cores'] ?? 0);
            $threads = (int) ($row['cpu_threads'] ?? 0);
            if ($sockets > 0 && $cores > 0 && $threads > 0) {
                continue;
            }
            $total = max(1, (int) ($row['cpu'] ?? 1));
            $update->execute([
                'cpu_sockets' => 1,
                'cpu_cores' => $total,
                'cpu_threads' => 1,
                'id' => (int) $row['id'],
            ]);
        }
    }

    private function extractPrefix(string $cidr): ?int
    {
        if (preg_match('/\/(\d{1,3})$/', $cidr, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }
}
