<?php

declare(strict_types=1);
namespace Nbkvm\Repositories;
use Nbkvm\Support\Database;
use PDO;
class ImageRepository
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
        return $this->db()->query('SELECT * FROM images ORDER BY created_at DESC')->fetchAll();
    }
    public function find(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM images WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    public function create(array $data): int
    {
        $db = $this->db();
        $stmt = $db->prepare('INSERT INTO images (name, original_name, path, extension, size_bytes, created_at) VALUES (:name, :original_name, :path, :extension, :size_bytes, :created_at)');
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    }
    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare('DELETE FROM images WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
