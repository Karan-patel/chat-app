<?php
declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Message;

interface MessageRepository
{
    public function store(int $groupId, int $userId, string $content): Message;

    /** @return Message[] */
    public function byGroup(int $groupId): array;
}