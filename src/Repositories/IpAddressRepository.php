<?php

declare(strict_types=1);
namespace Nbkvm\Repositories;
use Nbkvm\Support\Database;
use PDO;
class IpAddressRepository
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
        $stmt = $db->prepare('INSERT INTO ip_pool_addresses (pool_id, ip_address, status, vm_id, created_at, updated_at) VALUES (:pool_id, :ip_address, :status, :vm_id, :created_at, :updated_at)');
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    }
    public function all(): array
    {
        return $this->db()->query('SELECT a.*, p.name AS pool_name FROM ip_pool_addresses a LEFT JOIN ip_pools p ON p.id = a.pool_id ORDER BY a.id ASC')->fetchAll();
    }
    public function allByPool(int $poolId): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM ip_pool_addresses WHERE pool_id = :pool_id ORDER BY id ASC');
        $stmt->execute(['pool_id' => $poolId]);
        return $stmt->fetchAll();
    }
    public function findFree(int $poolId): ?array
    {
        $stmt = $this->db()->prepare("SELECT * FROM ip_pool_addresses WHERE pool_id = :pool_id AND status = 'free' ORDER BY id ASC LIMIT 1");
        $stmt->execute(['pool_id' => $poolId]);
        return $stmt->fetch() ?: null;
    }
    public function findByVmId(int $vmId): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM ip_pool_addresses WHERE vm_id = :vm_id LIMIT 1');
        $stmt->execute(['vm_id' => $vmId]);
        return $stmt->fetch() ?: null;
    }
    public function assign(int $id, int $vmId): void
    {
        $stmt = $this->db()->prepare("UPDATE ip_pool_addresses SET status='allocated', vm_id=:vm_id, updated_at=:updated_at WHERE id=:id");
        $stmt->execute(['vm_id' => $vmId, 'updated_at' => date('c'), 'id' => $id]);
    }
    public function releaseByVmId(int $vmId): void
    {
        $stmt = $this->db()->prepare("UPDATE ip_pool_addresses SET status='free', vm_id=NULL, updated_at=:updated_at WHERE vm_id=:vm_id");
        $stmt->execute(['updated_at' => date('c'), 'vm_id' => $vmId]);
    }
    public function deleteByPool(int $poolId): void
    {
        $stmt = $this->db()->prepare('DELETE FROM ip_pool_addresses WHERE pool_id = :pool_id');
        $stmt->execute(['pool_id' => $poolId]);
    }
}
