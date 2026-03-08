<?php

declare(strict_types=1);
namespace Nbkvm\Repositories;
use Nbkvm\Support\Database;
use PDO;
class SnapshotRepository
{
    public function __construct(private readonly ?PDO $pdo = null)
    {
    }
    private function db(): PDO
    {
        return $this->pdo ?? (new Database())->pdo();
    }
    public function create(array $data): int
    {
        $db = $this->db();
        $stmt = $db->prepare('INSERT INTO snapshots (vm_id, name, status, created_at) VALUES (:vm_id, :name, :status, :created_at)');
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    }
    public function all(): array
    {
        return $this->db()->query('SELECT s.*, v.name AS vm_name FROM snapshots s LEFT JOIN vms v ON v.id = s.vm_id ORDER BY s.id DESC')->fetchAll();
    }
    public function deleteByVmAndName(int $vmId, string $name): void
    {
        $stmt = $this->db()->prepare('DELETE FROM snapshots WHERE vm_id = :vm_id AND name = :name');
        $stmt->execute(['vm_id' => $vmId, 'name' => $name]);
    }
    public function deleteByName(string $name): void
    {
        $stmt = $this->db()->prepare('DELETE FROM snapshots WHERE name = :name');
        $stmt->execute(['name' => $name]);
    }
}
