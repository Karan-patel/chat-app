<?php
declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Group;

interface GroupRepository
{
    public function create(string $name): Group;

    public function addMember(int $groupId, int $userId): void;

    public function exists(int $groupId): bool;

    public function isMember(int $groupId, int $userId): bool;
}