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
        $stmt = $db->prepare('INSERT INTO templates (name, vmid_hint, image_id, image_type, os_type, os_source, os_variant, cpu, cpu_type, cpu_numa, cpu_limit_percent, cpu_units, memory_mb, memory_min_mb, memory_max_mb, balloon_enabled, disk_size_gb, disk_bus, scsi_controller, network_name, notes, cloud_init_enabled, cloud_init_user, cloud_init_password, cloud_init_ssh_key, cloud_init_hostname, cloud_init_dns_servers, cloud_init_search_domain, cloud_init_extra_user_data, virtualization_mode, cpu_sockets, cpu_cores, cpu_threads, machine_type, firmware_type, qemu_agent_enabled, display_type, serial_console_enabled, gpu_type, autostart_default, memory_overcommit_percent, disk_overcommit_enabled, disks_json, nics_json, created_at) VALUES (:name, :vmid_hint, :image_id, :image_type, :os_type, :os_source, :os_variant, :cpu, :cpu_type, :cpu_numa, :cpu_limit_percent, :cpu_units, :memory_mb, :memory_min_mb, :memory_max_mb, :balloon_enabled, :disk_size_gb, :disk_bus, :scsi_controller, :network_name, :notes, :cloud_init_enabled, :cloud_init_user, :cloud_init_password, :cloud_init_ssh_key, :cloud_init_hostname, :cloud_init_dns_servers, :cloud_init_search_domain, :cloud_init_extra_user_data, :virtualization_mode, :cpu_sockets, :cpu_cores, :cpu_threads, :machine_type, :firmware_type, :qemu_agent_enabled, :display_type, :serial_console_enabled, :gpu_type, :autostart_default, :memory_overcommit_percent, :disk_overcommit_enabled, :disks_json, :nics_json, :created_at)');
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db()->prepare('UPDATE templates SET name = :name, vmid_hint = :vmid_hint, image_id = :image_id, image_type = :image_type, os_type = :os_type, os_source = :os_source, os_variant = :os_variant, cpu = :cpu, cpu_type = :cpu_type, cpu_numa = :cpu_numa, cpu_limit_percent = :cpu_limit_percent, cpu_units = :cpu_units, memory_mb = :memory_mb, memory_min_mb = :memory_min_mb, memory_max_mb = :memory_max_mb, balloon_enabled = :balloon_enabled, disk_size_gb = :disk_size_gb, disk_bus = :disk_bus, scsi_controller = :scsi_controller, network_name = :network_name, notes = :notes, cloud_init_enabled = :cloud_init_enabled, cloud_init_user = :cloud_init_user, cloud_init_password = :cloud_init_password, cloud_init_ssh_key = :cloud_init_ssh_key, cloud_init_hostname = :cloud_init_hostname, cloud_init_dns_servers = :cloud_init_dns_servers, cloud_init_search_domain = :cloud_init_search_domain, cloud_init_extra_user_data = :cloud_init_extra_user_data, virtualization_mode = :virtualization_mode, cpu_sockets = :cpu_sockets, cpu_cores = :cpu_cores, cpu_threads = :cpu_threads, machine_type = :machine_type, firmware_type = :firmware_type, qemu_agent_enabled = :qemu_agent_enabled, display_type = :display_type, serial_console_enabled = :serial_console_enabled, gpu_type = :gpu_type, autostart_default = :autostart_default, memory_overcommit_percent = :memory_overcommit_percent, disk_overcommit_enabled = :disk_overcommit_enabled, disks_json = :disks_json, nics_json = :nics_json WHERE id = :id');
        $stmt->execute($data + ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare('DELETE FROM templates WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
