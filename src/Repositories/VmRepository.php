<?php

declare(strict_types=1);

namespace Nbkvm\Repositories;

use Nbkvm\Support\Database;
use PDO;

class VmRepository
{
    public function __construct(private readonly ?PDO $pdo = null)
    {
    }

    private function db(): PDO
    {
        return $this->pdo ?? (new Database())->pdo();
    }

    public function all(): array
    {
        return $this->db()->query('SELECT v.*, t.name AS template_name, p.name AS ip_pool_name FROM vms v LEFT JOIN templates t ON t.id = v.template_id LEFT JOIN ip_pools p ON p.id = v.ip_pool_id ORDER BY v.created_at DESC')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM vms WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM vms WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $db = $this->db();
        $stmt = $db->prepare('INSERT INTO vms (name, template_id, cpu, memory_mb, disk_path, disk_size_gb, network_name, ip_pool_id, status, ip_address, xml_path, cloud_init_iso_path, vnc_display, expires_at, expire_action, expire_grace_days, expired_at, nics_json, created_at) VALUES (:name, :template_id, :cpu, :memory_mb, :disk_path, :disk_size_gb, :network_name, :ip_pool_id, :status, :ip_address, :xml_path, :cloud_init_iso_path, :vnc_display, :expires_at, :expire_action, :expire_grace_days, :expired_at, :nics_json, :created_at)');
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?string $ip = null, ?string $vncDisplay = null): void
    {
        $stmt = $this->db()->prepare('UPDATE vms SET status = :status, ip_address = :ip_address, vnc_display = :vnc_display, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'ip_address' => $ip,
            'vnc_display' => $vncDisplay,
            'updated_at' => date('c'),
            'id' => $id,
        ]);
    }

    public function updateProvisioningData(int $id, ?string $cloudInitIsoPath, ?string $ipAddress, array $nics): void
    {
        $stmt = $this->db()->prepare('UPDATE vms SET cloud_init_iso_path = :cloud_init_iso_path, ip_address = :ip_address, nics_json = :nics_json, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'cloud_init_iso_path' => $cloudInitIsoPath,
            'ip_address' => $ipAddress,
            'nics_json' => json_encode($nics, JSON_UNESCAPED_UNICODE),
            'updated_at' => date('c'),
            'id' => $id,
        ]);
    }

    public function markExpired(int $id, string $status): void
    {
        $stmt = $this->db()->prepare('UPDATE vms SET status = :status, expired_at = :expired_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'expired_at' => date('c'),
            'updated_at' => date('c'),
            'id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare('DELETE FROM vms WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
