<?php
declare(strict_types=1);

namespace Tests\Infrastructure;

use Tests\TestCase;
use App\Infrastructure\Persistence\SQLite\SQLiteUserRepository;

final class SQLiteUserRepositoryTest extends TestCase
{
    public function test_user_is_created_if_not_exists(): void
    {
        $repo = new SQLiteUserRepository($this->pdo);

        $user = $repo->findOrCreate('karan');

        $this->assertSame('karan', $user->identifier());
        $this->assertGreaterThan(0, $user->id());
    }

    public function test_existing_user_is_returned(): void
    {
        $repo = new SQLiteUserRepository($this->pdo);

        $first = $repo->findOrCreate('karan');
        $second = $repo->findOrCreate('karan');

        $this->assertSame($first->id(), $second->id());
    }
}