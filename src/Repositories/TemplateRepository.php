<?php

declare(strict_types=1);
namespace Nbkvm\Repositories;
use Nbkvm\Support\Database;
use PDO;
class TemplateRepository
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
        return $this->db()->query('SELECT t.*, i.name AS image_name FROM templates t LEFT JOIN images i ON i.id = t.image_id ORDER BY t.created_at DESC')->fetchAll();
    }
    public function find(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM templates WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    public function create(array $data): int
    {
        $db = $this->db();
        $stmt = $db->prepare('INSERT INTO templates (name, image_id, image_type, os_variant, cpu, memory_mb, disk_size_gb, disk_bus, network_name, notes, created_at) VALUES (:name, :image_id, :image_type, :os_variant, :cpu, :memory_mb, :disk_size_gb, :disk_bus, :network_name, :notes, :created_at)');
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    }
}
