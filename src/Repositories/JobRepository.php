<?php

declare(strict_types=1);
namespace Nbkvm\Repositories;
use Nbkvm\Support\Database;
use PDO;
class JobRepository
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
        $stmt = $db->prepare('INSERT INTO jobs (name, target_type, target_name, status, output, created_at, updated_at) VALUES (:name, :target_type, :target_name, :status, :output, :created_at, :updated_at)');
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    }
    public function update(int $id, string $status, ?string $output = null): void
    {
        $stmt = $this->db()->prepare('UPDATE jobs SET status = :status, output = :output, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(['status' => $status, 'output' => $output, 'updated_at' => date('c'), 'id' => $id]);
    }
    public function latest(int $limit = 30): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM jobs ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
