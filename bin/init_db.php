<?php

declare(strict_types=1);
require dirname(__DIR__) . '/src/Support/helpers.php';
require dirname(__DIR__) . '/src/Support/Autoload.php';
Nbkvm\Support\Autoload::register();
$database = new Nbkvm\Support\Database();
$pdo = $database->pdo();
$queries = [
    "CREATE TABLE IF NOT EXISTS images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        original_name TEXT NOT NULL,
        path TEXT NOT NULL,
        extension TEXT NOT NULL,
        size_bytes INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL
    )",
    "CREATE TABLE IF NOT EXISTS templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        image_id INTEGER NOT NULL,
        image_type TEXT NOT NULL,
        os_variant TEXT NOT NULL,
        cpu INTEGER NOT NULL,
        memory_mb INTEGER NOT NULL,
        disk_size_gb INTEGER NOT NULL,
        disk_bus TEXT NOT NULL,
        network_name TEXT NOT NULL,
        notes TEXT DEFAULT '',
        created_at TEXT NOT NULL,
        FOREIGN KEY(image_id) REFERENCES images(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS vms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        template_id INTEGER NOT NULL,
        cpu INTEGER NOT NULL,
        memory_mb INTEGER NOT NULL,
        disk_path TEXT NOT NULL,
        disk_size_gb INTEGER NOT NULL,
        network_name TEXT NOT NULL,
        status TEXT NOT NULL,
        ip_address TEXT DEFAULT NULL,
        xml_path TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT DEFAULT NULL,
        FOREIGN KEY(template_id) REFERENCES templates(id) ON DELETE RESTRICT
    )",
];
foreach ($queries as $query) {
    $pdo->exec($query);
}
echo "Database initialized: " . config('database_path') . PHP_EOL;
