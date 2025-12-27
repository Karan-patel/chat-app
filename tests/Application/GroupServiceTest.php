<?php
declare(strict_types=1);

namespace Tests\Application;

use Tests\TestCase;
use App\Application\Service\GroupService;
use App\Application\Exception\ValidationException;
use App\Application\Exception\NotFoundException;
use App\Infrastructure\Persistence\SQLite\{
    SQLiteUserRepository,
    SQLiteGroupRepository
};

final class GroupServiceTest extends TestCase
{
    private GroupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GroupService(
            new SQLiteGroupRepository($this->pdo),
            new SQLiteUserRepository($this->pdo)
        );
    }

    public function test_group_can_be_created(): void
    {
        $group = $this->service->create('General', 'karan');

        $this->assertSame('General', $group->name());
        $this->assertGreaterThan(0, $group->id());
    }

    public function test_empty_group_name_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create('', 'karan');
    }

    public function test_user_can_join_existing_group(): void
    {
        $group = $this->service->create('General', 'karan');

        $this->service->join($group->id(), 'another-user');

        $this->assertTrue(true); // No exception = success
    }

    public function test_joining_non_existing_group_fails(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service->join(999, 'karan');
    }
}