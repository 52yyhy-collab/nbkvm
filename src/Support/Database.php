<?php

declare(strict_types=1);
namespace Nbkvm\Support;
use PDO;
class Database
{
    private PDO $pdo;
    public function __construct()
    {
        $driver = (string) config('database.driver', 'sqlite');
        if ($driver === 'mysql') {
            $host = (string) config('database.mysql.host');
            $port = (int) config('database.mysql.port');
            $database = (string) config('database.mysql.database');
            $charset = (string) config('database.mysql.charset', 'utf8mb4');
            $username = (string) config('database.mysql.username');
            $password = (string) config('database.mysql.password');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return;
        }
        $path = (string) config('database.sqlite_path');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }
    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
