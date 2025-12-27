<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\SQLite;

use PDO;
use App\Domain\Entity\User;
use App\Domain\Repository\UserRepository;

final class SQLiteUserRepository implements UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findOrCreate(string $identifier): User
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, identifier FROM users WHERE identifier = :id'
        );
        $stmt->execute(['id' => $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return new User((int)$row['id'], $row['identifier']);
        }

        $this->pdo
            ->prepare('INSERT INTO users (identifier) VALUES (:id)')
            ->execute(['id' => $identifier]);

        return new User((int)$this->pdo->lastInsertId(), $identifier);
    }
}