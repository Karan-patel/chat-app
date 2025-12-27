<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\SQLite;

use PDO;
use DateTimeImmutable;
use App\Domain\Entity\Message;
use App\Domain\Repository\MessageRepository;

final class SQLiteMessageRepository implements MessageRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function store(int $groupId, int $userId, string $content): Message
    {
        $createdAt = (new DateTimeImmutable())->format(DATE_ATOM);

        $this->pdo->prepare(
            'INSERT INTO messages (group_id, user_id, content, created_at)
             VALUES (:g, :u, :c, :t)'
        )->execute([
            'g' => $groupId,
            'u' => $userId,
            'c' => $content,
            't' => $createdAt
        ]);

        return new Message(
            (int)$this->pdo->lastInsertId(),
            $groupId,
            $userId,
            $content,
            $createdAt
        );
    }

    public function byGroup(int $groupId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM messages WHERE group_id = :g ORDER BY id ASC'
        );
        $stmt->execute(['g' => $groupId]);

        return array_map(
            fn(array $r) => new Message(
                (int)$r['id'],
                (int)$r['group_id'],
                (int)$r['user_id'],
                $r['content'],
                $r['created_at']
            ),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}