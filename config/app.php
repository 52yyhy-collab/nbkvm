<?php

declare(strict_types=1);
$basePath = dirname(__DIR__);
$storageRoot = getenv('NBKVM_STORAGE_ROOT') ?: '/var/libvirt/images/nbkvm';
return [
    'app_name' => 'NBKVM',
    'base_path' => $basePath,
    'database_path' => $basePath . '/storage/database/nbkvm.sqlite',
    'storage_root' => $storageRoot,
    'upload_path' => getenv('NBKVM_UPLOAD_PATH') ?: ($storageRoot . '/uploads'),
    'template_path' => getenv('NBKVM_TEMPLATE_PATH') ?: ($storageRoot . '/templates'),
    'vm_path' => getenv('NBKVM_VM_PATH') ?: ($storageRoot . '/vms'),
    'log_path' => $basePath . '/storage/logs/app.log',
    'max_upload_bytes' => 50 * 1024 * 1024 * 1024,
    'allowed_extensions' => ['iso', 'qcow2', 'img', 'raw'],
    'libvirt' => [
        'uri' => 'qemu:///system',
        'qemu_img' => 'qemu-img',
        'default_network' => 'default',
        'default_disk_bus' => 'virtio',
        'default_os_variant' => 'generic',
        'default_graphics' => 'vnc',
    ],
];
