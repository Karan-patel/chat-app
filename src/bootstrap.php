<?php

use App\Infrastructure\Persistence\SQLite\{
    SQLiteConnection,
    SQLiteUserRepository,
    SQLiteGroupRepository,
    SQLiteMessageRepository
};
use App\Application\Service\{
    GroupService,
    MessageService
};

$pdo = SQLiteConnection::connect();

$userRepo = new SQLiteUserRepository($pdo);
$groupRepo = new SQLiteGroupRepository($pdo);
$messageRepo = new SQLiteMessageRepository($pdo);

$groupService = new GroupService($groupRepo, $userRepo);
$messageService = new MessageService($messageRepo, $groupRepo, $userRepo);