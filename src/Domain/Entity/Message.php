<?php
declare(strict_types=1);

namespace App\Domain\Entity;

final class Message
{
    public function __construct(
        private readonly int $id,
        private readonly int $groupId,
        private readonly int $userId,
        private readonly string $content,
        private readonly string $createdAt
    ) {}

    public function id(): int { return $this->id; }
    public function groupId(): int { return $this->groupId; }
    public function userId(): int { return $this->userId; }
    public function content(): string { return $this->content; }
    public function createdAt(): string { return $this->createdAt; }
}