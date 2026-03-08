<?php

declare(strict_types=1);
return [
    'app_name' => 'NBKVM',
    'base_path' => dirname(__DIR__),
    'database_path' => dirname(__DIR__) . '/storage/database/nbkvm.sqlite',
    'upload_path' => dirname(__DIR__) . '/storage/uploads',
    'template_path' => dirname(__DIR__) . '/storage/templates',
    'vm_path' => dirname(__DIR__) . '/storage/vms',
    'log_path' => dirname(__DIR__) . '/storage/logs/app.log',
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
