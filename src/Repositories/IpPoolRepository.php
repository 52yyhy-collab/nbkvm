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

    public function findByName(string $name): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM ip_pools WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        return $stmt->fetch() ?: null;
    }

    public function findByNetworkId(int $networkId, ?string $family = null): ?array
    {
        $sql = 'SELECT * FROM ip_pools WHERE network_id = :network_id';
        $params = ['network_id' => $networkId];
        if ($family !== null) {
            $sql .= ' AND family = :family';
            $params['family'] = $family;
        }
        $sql .= ' ORDER BY id ASC LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    public function findAllByNetworkId(int $networkId, ?string $family = null): array
    {
        $sql = 'SELECT * FROM ip_pools WHERE network_id = :network_id';
        $params = ['network_id' => $networkId];
        if ($family !== null) {
            $sql .= ' AND family = :family';
            $params['family'] = $family;
        }
        $sql .= ' ORDER BY id ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findByNetworkName(string $networkName, ?string $family = null): ?array
    {
        $sql = 'SELECT * FROM ip_pools WHERE network_name = :network_name';
        $params = ['network_name' => $networkName];
        if ($family !== null) {
            $sql .= ' AND family = :family';
            $params['family'] = $family;
        }
        $sql .= ' ORDER BY id ASC LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    public function findAllByNetworkName(string $networkName, ?string $family = null): array
    {
        $sql = 'SELECT * FROM ip_pools WHERE network_name = :network_name';
        $params = ['network_name' => $networkName];
        if ($family !== null) {
            $sql .= ' AND family = :family';
            $params['family'] = $family;
        }
        $sql .= ' ORDER BY id ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $db = $this->db();
        $stmt = $db->prepare('INSERT INTO ip_pools (name, network_id, network_name, family, gateway, prefix_length, dns_servers, start_ip, end_ip, interface_name, created_at) VALUES (:name, :network_id, :network_name, :family, :gateway, :prefix_length, :dns_servers, :start_ip, :end_ip, :interface_name, :created_at)');
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db()->prepare('UPDATE ip_pools SET name = :name, network_id = :network_id, network_name = :network_name, family = :family, gateway = :gateway, prefix_length = :prefix_length, dns_servers = :dns_servers, start_ip = :start_ip, end_ip = :end_ip, interface_name = :interface_name WHERE id = :id');
        $stmt->execute($data + ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare('DELETE FROM ip_pools WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
