<?php

declare(strict_types=1);
namespace Nbkvm\Repositories;
use Nbkvm\Support\Database;
use PDO;
class SettingRepository
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
        return $this->db()->query('SELECT * FROM settings ORDER BY key_name ASC')->fetchAll();
    }
    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->db()->prepare('SELECT value_text FROM settings WHERE key_name = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    }
    public function set(string $key, string $value): void
    {
        $stmt = $this->db()->prepare('INSERT INTO settings (key_name, value_text, updated_at) VALUES (:key_name, :value_text, :updated_at) ON CONFLICT(key_name) DO UPDATE SET value_text=excluded.value_text, updated_at=excluded.updated_at');
        $stmt->execute([
            'key_name' => $key,
            'value_text' => $value,
            'updated_at' => date('c'),
        ]);
    }
}
