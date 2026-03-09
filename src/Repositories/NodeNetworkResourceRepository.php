<?php

declare(strict_types=1);

namespace Nbkvm\Repositories;

use Nbkvm\Support\Database;
use PDO;

class NodeNetworkResourceRepository
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
        return $this->db()->query('SELECT * FROM node_network_resources ORDER BY id DESC')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM node_network_resources WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM node_network_resources WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $db = $this->db();
        $stmt = $db->prepare('INSERT INTO node_network_resources (name, type, parent, ports_json, vlan_id, bond_mode, bridge_vlan_aware, cidr, gateway, ipv6_cidr, ipv6_gateway, mtu, autostart, comments, managed_on_host, last_apply_status, last_apply_output, created_at, updated_at) VALUES (:name, :type, :parent, :ports_json, :vlan_id, :bond_mode, :bridge_vlan_aware, :cidr, :gateway, :ipv6_cidr, :ipv6_gateway, :mtu, :autostart, :comments, :managed_on_host, :last_apply_status, :last_apply_output, :created_at, :updated_at)');
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db()->prepare('UPDATE node_network_resources SET name = :name, type = :type, parent = :parent, ports_json = :ports_json, vlan_id = :vlan_id, bond_mode = :bond_mode, bridge_vlan_aware = :bridge_vlan_aware, cidr = :cidr, gateway = :gateway, ipv6_cidr = :ipv6_cidr, ipv6_gateway = :ipv6_gateway, mtu = :mtu, autostart = :autostart, comments = :comments, managed_on_host = :managed_on_host, last_apply_status = :last_apply_status, last_apply_output = :last_apply_output, updated_at = :updated_at WHERE id = :id');
        $stmt->execute($data + ['id' => $id]);
    }

    public function updateApplyState(int $id, array $data): void
    {
        $stmt = $this->db()->prepare('UPDATE node_network_resources SET managed_on_host = :managed_on_host, last_apply_status = :last_apply_status, last_apply_output = :last_apply_output, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'managed_on_host' => (int) ($data['managed_on_host'] ?? 0),
            'last_apply_status' => $data['last_apply_status'] ?? null,
            'last_apply_output' => $data['last_apply_output'] ?? null,
            'updated_at' => date('c'),
            'id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare('DELETE FROM node_network_resources WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
