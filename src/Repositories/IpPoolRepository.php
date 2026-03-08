<?php

declare(strict_types=1);
namespace Nbkvm\Repositories;
use Nbkvm\Support\Database;
use PDO;
class IpPoolRepository
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
        return $this->db()->query('SELECT * FROM ip_pools ORDER BY id DESC')->fetchAll();
    }
    public function find(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM ip_pools WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    public function create(array $data): int
    {
        $db = $this->db();
        $stmt = $db->prepare('INSERT INTO ip_pools (name, network_name, gateway, prefix_length, dns_servers, start_ip, end_ip, interface_name, created_at) VALUES (:name, :network_name, :gateway, :prefix_length, :dns_servers, :start_ip, :end_ip, :interface_name, :created_at)');
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    }
    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare('DELETE FROM ip_pools WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
