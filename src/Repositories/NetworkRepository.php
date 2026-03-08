<?php

declare(strict_types=1);
namespace Nbkvm\Repositories;
use Nbkvm\Support\Database;
use PDO;
class NetworkRepository
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
        return $this->db()->query('SELECT * FROM networks ORDER BY id DESC')->fetchAll();
    }
    public function findByName(string $name): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM networks WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        return $stmt->fetch() ?: null;
    }
    public function create(array $data): int
    {
        $db = $this->db();
        $stmt = $db->prepare('INSERT INTO networks (name, cidr, gateway, bridge_name, dhcp_start, dhcp_end, libvirt_managed, autostart, created_at) VALUES (:name, :cidr, :gateway, :bridge_name, :dhcp_start, :dhcp_end, :libvirt_managed, :autostart, :created_at)');
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    }
    public function updateByName(string $name, array $data): void
    {
        $stmt = $this->db()->prepare('UPDATE networks SET cidr=:cidr, gateway=:gateway, bridge_name=:bridge_name, dhcp_start=:dhcp_start, dhcp_end=:dhcp_end, libvirt_managed=:libvirt_managed, autostart=:autostart WHERE name=:name');
        $stmt->execute($data + ['name' => $name]);
    }
}
