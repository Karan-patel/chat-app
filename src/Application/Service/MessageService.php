<?php
declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Repository\MessageRepository;
use App\Domain\Repository\GroupRepository;
use App\Domain\Repository\UserRepository;
use App\Domain\Entity\Message;
use App\Application\Exception\NotFoundException;
use App\Application\Exception\ValidationException;
use App\Application\Exception\ForbiddenException;

final class MessageService
{
    public function __construct(
        private readonly MessageRepository $messages,
        private readonly GroupRepository   $groups,
        private readonly UserRepository    $users
    )
    {
    }

    public function send(
        int    $groupId,
        string $content,
        string $userIdentifier
    ): Message
    {
        $this->assertValidContent($content);

        if (!$this->groups->exists($groupId)) {
            throw new NotFoundException('Group not found');
        }

        $user = $this->users->findOrCreate($userIdentifier);

        if (!$this->groups->isMember($groupId, $user->id())) {
            throw new ForbiddenException('User is not a member of this group');
        }

        return $this->messages->store(
            $groupId,
            $user->id(),
            $content
        );
    }

    /** @return Message[] */
    public function list(
        int    $groupId,
        string $userIdentifier
    ): array
    {
        if (!$this->groups->exists($groupId)) {
            throw new NotFoundException('Group not found');
        }

        $user = $this->users->findOrCreate($userIdentifier);

        if (!$this->groups->isMember($groupId, $user->id())) {
            throw new ForbiddenException('User is not a member of this group');
        }

        return $this->messages->byGroup($groupId);
    }

    private function assertValidContent(string $content): void
    {
        if (trim($content) === '') {
            throw new ValidationException('Message must not be empty');
        }

        if (mb_strlen($content) > 2000) {
            throw new ValidationException('Message too long');
        }
    }
}