<?php
declare(strict_types=1);

namespace Tests\Application;

use App\Application\Exception\ForbiddenException;
use Tests\TestCase;
use App\Application\Service\MessageService;
use App\Application\Exception\ValidationException;
use App\Application\Exception\NotFoundException;
use App\Infrastructure\Persistence\SQLite\{
    SQLiteUserRepository,
    SQLiteGroupRepository,
    SQLiteMessageRepository
};

final class MessageServiceTest extends TestCase
{
    private MessageService $service;
    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();

        // Repositories
        $userRepository    = new SQLiteUserRepository($this->pdo);
        $groupRepository   = new SQLiteGroupRepository($this->pdo);
        $messageRepository = new SQLiteMessageRepository($this->pdo);

        // Service under test
        $this->service = new MessageService(
            $messageRepository,
            $groupRepository,
            $userRepository
        );

        // Test fixture: group with members
        $group = $groupRepository->create('General');
        $this->groupId = $group->id();

        foreach (['karan', 'a', 'b'] as $username) {
            $user = $userRepository->findOrCreate($username);
            $groupRepository->addMember($this->groupId, $user->id());
        }
    }

    public function test_message_can_be_sent(): void
    {
        $message = $this->service->send(
            $this->groupId,
            'Hello world',
            'karan'
        );

        $this->assertSame('Hello world', $message->content());
        $this->assertSame($this->groupId, $message->groupId());
    }

    public function test_empty_message_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->send(
            $this->groupId,
            '',
            'karan'
        );
    }

    public function test_messages_can_be_listed_by_non_group_member(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->service->send($this->groupId, 'One', 'outsider');
    }

    public function test_messages_can_be_listed(): void
    {
        $this->service->send($this->groupId, 'One', 'a');
        $this->service->send($this->groupId, 'Two', 'b');

        $messages = $this->service->list($this->groupId, 'a');

        $this->assertCount(2, $messages);
        $this->assertSame('One', $messages[0]->content());
    }

    public function test_sending_message_to_unknown_group_fails(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service->send(999, 'Hi', 'karan');
    }
}