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
        return $this->db()->query('SELECT v.*, t.name AS template_name FROM vms v LEFT JOIN templates t ON t.id = v.template_id ORDER BY v.created_at DESC')->fetchAll();
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
        $stmt = $this->db()->prepare('INSERT INTO vms (name, template_id, cpu, memory_mb, disk_path, disk_size_gb, network_name, status, ip_address, xml_path, created_at) VALUES (:name, :template_id, :cpu, :memory_mb, :disk_path, :disk_size_gb, :network_name, :status, :ip_address, :xml_path, :created_at)');
        $stmt->execute($data);
        return (int) $this->db()->lastInsertId();
    }
    public function updateStatus(int $id, string $status, ?string $ip = null): void
    {
        $stmt = $this->db()->prepare('UPDATE vms SET status = :status, ip_address = :ip_address, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'ip_address' => $ip,
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
