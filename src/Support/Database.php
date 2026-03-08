<?php

declare(strict_types=1);
namespace Nbkvm\Support;
use PDO;
class Database
{
    private PDO $pdo;
    public function __construct(?string $path = null)
    {
        $path ??= config('database_path');
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
