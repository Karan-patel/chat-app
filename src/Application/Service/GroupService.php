<?php
declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Repository\GroupRepository;
use App\Domain\Repository\UserRepository;
use App\Domain\Entity\Group;
use App\Application\Exception\NotFoundException;
use App\Application\Exception\ValidationException;

final class GroupService
{
    public function __construct(
        private readonly GroupRepository $groups,
        private readonly UserRepository  $users
    )
    {
    }

    public function create(string $name, string $userIdentifier): Group
    {
        $this->assertValidName($name);

        $user = $this->users->findOrCreate($userIdentifier);
        $group = $this->groups->create($name);

        $this->groups->addMember($group->id(), $user->id());

        return $group;
    }

    public function join(int $groupId, string $userIdentifier): void
    {
        if (!$this->groups->exists($groupId)) {
            throw new NotFoundException('Group not found');
        }

        $user = $this->users->findOrCreate($userIdentifier);
        $this->groups->addMember($groupId, $user->id());
    }

    private function assertValidName(string $name): void
    {
        if (trim($name) === '') {
            throw new ValidationException('Group name must not be empty');
        }

        if (mb_strlen($name) > 100) {
            throw new ValidationException('Group name too long');
        }
    }
}