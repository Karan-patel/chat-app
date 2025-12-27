<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\SQLite;

use PDO;
use App\Domain\Entity\Group;
use App\Domain\Repository\GroupRepository;

final class SQLiteGroupRepository implements GroupRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(string $name): Group
    {
        $this->pdo
            ->prepare('INSERT INTO groups (name) VALUES (:name)')
            ->execute(['name' => $name]);

        return new Group((int)$this->pdo->lastInsertId(), $name);
    }

    public function addMember(int $groupId, int $userId): void
    {
        $this->pdo
            ->prepare(
                'INSERT OR IGNORE INTO group_members (group_id, user_id)
                 VALUES (:g, :u)'
            )
            ->execute(['g' => $groupId, 'u' => $userId]);
    }

    public function exists(int $groupId): bool
    {
        $stmt = $this->pdo
            ->prepare('SELECT 1 FROM groups WHERE id = :id');
        $stmt->execute(['id' => $groupId]);

        return (bool)$stmt->fetchColumn();
    }

    public function isMember(int $groupId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM group_members WHERE group_id = :g AND user_id = :u'
        );

        $stmt->execute([
            'g' => $groupId,
            'u' => $userId
        ]);

        return (bool)$stmt->fetchColumn();
    }
}