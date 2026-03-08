<?php

declare(strict_types=1);
require dirname(__DIR__) . '/src/Support/helpers.php';
require dirname(__DIR__) . '/src/Support/Autoload.php';
Nbkvm\Support\Autoload::register();
$pdo = (new Nbkvm\Support\Database())->pdo();
$driver = (string) config('database.driver', 'sqlite');
$idColumn = $driver === 'mysql'
    ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
    : 'INTEGER PRIMARY KEY AUTOINCREMENT';
$boolType = $driver === 'mysql' ? 'TINYINT(1)' : 'INTEGER';
$textPk = $driver === 'mysql' ? 'VARCHAR(191)' : 'TEXT';
$queries = [
    "CREATE TABLE IF NOT EXISTS users (
        id $idColumn,
        username VARCHAR(191) NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'admin',
        created_at TEXT NOT NULL
    )",
    "CREATE TABLE IF NOT EXISTS images (
        id $idColumn,
        name VARCHAR(191) NOT NULL,
        original_name VARCHAR(191) NOT NULL,
        path TEXT NOT NULL,
        extension VARCHAR(32) NOT NULL,
        size_bytes BIGINT NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL
    )",
    "CREATE TABLE IF NOT EXISTS templates (
        id $idColumn,
        name VARCHAR(191) NOT NULL UNIQUE,
        image_id BIGINT NOT NULL,
        image_type VARCHAR(32) NOT NULL,
        os_variant VARCHAR(191) NOT NULL,
        cpu INTEGER NOT NULL,
        memory_mb INTEGER NOT NULL,
        disk_size_gb INTEGER NOT NULL,
        disk_bus VARCHAR(50) NOT NULL,
        network_name VARCHAR(191) NOT NULL,
        notes TEXT DEFAULT '',
        cloud_init_enabled $boolType NOT NULL DEFAULT 0,
        cloud_init_user VARCHAR(191) DEFAULT NULL,
        cloud_init_password VARCHAR(191) DEFAULT NULL,
        cloud_init_ssh_key TEXT DEFAULT NULL,
        created_at TEXT NOT NULL
    )",
    "CREATE TABLE IF NOT EXISTS vms (
        id $idColumn,
        name VARCHAR(191) NOT NULL UNIQUE,
        template_id BIGINT NOT NULL,
        cpu INTEGER NOT NULL,
        memory_mb INTEGER NOT NULL,
        disk_path TEXT NOT NULL,
        disk_size_gb INTEGER NOT NULL,
        network_name VARCHAR(191) NOT NULL,
        status VARCHAR(50) NOT NULL,
        ip_address VARCHAR(191) DEFAULT NULL,
        xml_path TEXT NOT NULL,
        cloud_init_iso_path TEXT DEFAULT NULL,
        vnc_display VARCHAR(191) DEFAULT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS snapshots (
        id $idColumn,
        vm_id BIGINT NOT NULL,
        name VARCHAR(191) NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'created',
        created_at TEXT NOT NULL
    )",
    "CREATE TABLE IF NOT EXISTS audit_logs (
        id $idColumn,
        username VARCHAR(191) DEFAULT NULL,
        action VARCHAR(191) NOT NULL,
        target_type VARCHAR(100) DEFAULT NULL,
        target_name VARCHAR(191) DEFAULT NULL,
        detail TEXT DEFAULT NULL,
        created_at TEXT NOT NULL
    )",
];
foreach ($queries as $query) {
    $pdo->exec($query);
}
$username = (string) config('auth.default_username');
$exists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
$exists->execute(['username' => $username]);
if ((int) $exists->fetchColumn() === 0) {
    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, created_at) VALUES (:username, :password_hash, :role, :created_at)');
    $stmt->execute([
        'username' => $username,
        'password_hash' => password_hash((string) config('auth.default_password'), PASSWORD_DEFAULT),
        'role' => 'admin',
        'created_at' => date('c'),
    ]);
}
echo "Database initialized: " . ($driver === 'mysql' ? config('database.mysql.database') : config('database.sqlite_path')) . PHP_EOL;
