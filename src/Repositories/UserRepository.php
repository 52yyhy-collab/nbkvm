<?php

declare(strict_types=1);
namespace Nbkvm\Repositories;
use Nbkvm\Support\Database;
use PDO;
class UserRepository
{
    public function __construct(private readonly ?PDO $pdo = null)
    {
    }
    private function db(): PDO
    {
        return $this->pdo ?? (new Database())->pdo();
    }
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        return $stmt->fetch() ?: null;
    }
    public function updatePassword(int $id, string $hash): void
    {
        $stmt = $this->db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $stmt->execute(['password_hash' => $hash, 'id' => $id]);
    }
}
