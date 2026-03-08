<?php

declare(strict_types=1);
$basePath = dirname(__DIR__);
$storageRoot = getenv('NBKVM_STORAGE_ROOT') ?: '/var/libvirt/images/nbkvm';
$dbDriver = getenv('NBKVM_DB_DRIVER') ?: 'sqlite';
return [
    'app_name' => 'NBKVM',
    'base_path' => $basePath,
    'database' => [
        'driver' => $dbDriver,
        'sqlite_path' => getenv('NBKVM_SQLITE_PATH') ?: ($basePath . '/storage/database/nbkvm.sqlite'),
        'mysql' => [
            'host' => getenv('NBKVM_DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('NBKVM_DB_PORT') ?: 3306),
            'database' => getenv('NBKVM_DB_NAME') ?: 'nbkvm',
            'username' => getenv('NBKVM_DB_USER') ?: 'nbkvm',
            'password' => getenv('NBKVM_DB_PASS') ?: 'nbkvm',
            'charset' => 'utf8mb4',
        ],
    ],
    'storage_root' => $storageRoot,
    'upload_path' => getenv('NBKVM_UPLOAD_PATH') ?: ($storageRoot . '/uploads'),
    'template_path' => getenv('NBKVM_TEMPLATE_PATH') ?: ($storageRoot . '/templates'),
    'vm_path' => getenv('NBKVM_VM_PATH') ?: ($storageRoot . '/vms'),
    'log_path' => $basePath . '/storage/logs/app.log',
    'max_upload_bytes' => 50 * 1024 * 1024 * 1024,
    'allowed_extensions' => ['iso', 'qcow2', 'img', 'raw'],
    'auth' => [
        'default_username' => getenv('NBKVM_ADMIN_USER') ?: 'admin',
        'default_password' => getenv('NBKVM_ADMIN_PASS') ?: 'admin123456',
        'session_name' => 'nbkvm_session',
    ],
    'novnc' => [
        'base_url' => getenv('NBKVM_NOVNC_BASE_URL') ?: '',
        'path' => getenv('NBKVM_NOVNC_PATH') ?: '/vnc.html',
    ],
    'queue' => [
        'enabled' => (getenv('NBKVM_QUEUE_ENABLED') ?: '0') === '1',
    ],
    'cloud_init' => [
        'enabled' => true,
        'cloud_localds' => getenv('NBKVM_CLOUD_LOCALDS') ?: 'cloud-localds',
        'default_domain' => getenv('NBKVM_CLOUD_DOMAIN') ?: 'localdomain',
        'dns' => getenv('NBKVM_CLOUD_DNS') ?: '1.1.1.1,8.8.8.8',
    ],
    'virtualization' => [
        'default_mode' => 'kvm',
        'supported_modes' => ['kvm', 'qemu'],
        'default_machine' => 'pc',
        'supported_machines' => ['pc', 'q35'],
        'default_firmware' => 'bios',
        'supported_firmware' => ['bios', 'uefi'],
        'default_gpu' => 'cirrus',
        'supported_gpus' => ['cirrus', 'qxl', 'virtio', 'vga', 'none'],
    ],
    'defaults' => [
        'expire_action' => getenv('NBKVM_DEFAULT_EXPIRE_ACTION') ?: 'pause',
        'expire_grace_days' => (int) (getenv('NBKVM_EXPIRE_GRACE_DAYS') ?: 3),
        'upload_max_size_mb' => (int) (getenv('NBKVM_UPLOAD_MAX_MB') ?: 51200),
    ],
    'libvirt' => [
        'uri' => 'qemu:///system',
        'qemu_img' => 'qemu-img',
        'virsh' => 'virsh',
        'default_network' => 'default',
        'default_disk_bus' => 'virtio',
        'default_os_variant' => 'generic',
        'default_graphics' => 'vnc',
    ],
];
