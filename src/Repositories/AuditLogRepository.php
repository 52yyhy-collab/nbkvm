<?php

declare(strict_types=1);
namespace Nbkvm\Repositories;
use Nbkvm\Support\Database;
use PDO;
class AuditLogRepository
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
        $stmt = $db->prepare('INSERT INTO audit_logs (username, action, target_type, target_name, detail, created_at) VALUES (:username, :action, :target_type, :target_name, :detail, :created_at)');
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    }
    public function latest(int $limit = 50): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM audit_logs ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
