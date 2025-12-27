<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\SQLite;

use PDO;

final class SQLiteConnection
{
    public static function connect(): PDO
    {
        $dbPath = getenv('SQLITE_DB_PATH')
            ?: __DIR__ . '/../../../../data/database.sqlite';

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}