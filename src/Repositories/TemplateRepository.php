<?php

declare(strict_types=1);

namespace Nbkvm\Repositories;

use Nbkvm\Support\Database;
use PDO;

class TemplateRepository
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
        return $this->db()->query('SELECT t.*, i.name AS image_name, (SELECT COUNT(*) FROM vms v WHERE v.template_id = t.id) AS linked_vm_count FROM templates t LEFT JOIN images i ON i.id = t.image_id ORDER BY t.created_at DESC')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT t.*, i.name AS image_name, (SELECT COUNT(*) FROM vms v WHERE v.template_id = t.id) AS linked_vm_count FROM templates t LEFT JOIN images i ON i.id = t.image_id WHERE t.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM templates WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $db = $this->db();
        $stmt = $db->prepare('INSERT INTO templates (name, image_id, image_type, os_variant, cpu, memory_mb, disk_size_gb, disk_bus, network_name, notes, cloud_init_enabled, cloud_init_user, cloud_init_password, cloud_init_ssh_key, virtualization_mode, cpu_sockets, cpu_cores, cpu_threads, machine_type, firmware_type, gpu_type, autostart_default, memory_max_mb, memory_overcommit_percent, disk_overcommit_enabled, disks_json, nics_json, created_at) VALUES (:name, :image_id, :image_type, :os_variant, :cpu, :memory_mb, :disk_size_gb, :disk_bus, :network_name, :notes, :cloud_init_enabled, :cloud_init_user, :cloud_init_password, :cloud_init_ssh_key, :virtualization_mode, :cpu_sockets, :cpu_cores, :cpu_threads, :machine_type, :firmware_type, :gpu_type, :autostart_default, :memory_max_mb, :memory_overcommit_percent, :disk_overcommit_enabled, :disks_json, :nics_json, :created_at)');
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db()->prepare('UPDATE templates SET name = :name, image_id = :image_id, image_type = :image_type, os_variant = :os_variant, cpu = :cpu, memory_mb = :memory_mb, disk_size_gb = :disk_size_gb, disk_bus = :disk_bus, network_name = :network_name, notes = :notes, cloud_init_enabled = :cloud_init_enabled, cloud_init_user = :cloud_init_user, cloud_init_password = :cloud_init_password, cloud_init_ssh_key = :cloud_init_ssh_key, virtualization_mode = :virtualization_mode, cpu_sockets = :cpu_sockets, cpu_cores = :cpu_cores, cpu_threads = :cpu_threads, machine_type = :machine_type, firmware_type = :firmware_type, gpu_type = :gpu_type, autostart_default = :autostart_default, memory_max_mb = :memory_max_mb, memory_overcommit_percent = :memory_overcommit_percent, disk_overcommit_enabled = :disk_overcommit_enabled, disks_json = :disks_json, nics_json = :nics_json WHERE id = :id');
        $stmt->execute($data + ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare('DELETE FROM templates WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
