<?php

use App\Database;
use App\GroupController;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

require_once __DIR__ . '/../vendor/autoload.php';

class GroupControllerTest extends TestCase
{
    private $db;
    private $controller;

    protected function setUp(): void
    {
        $this->db = new Database(':memory:');

        // Initialize the database schema.
        if (method_exists($this->db, 'initializeSchema')) {
            $this->db->initializeSchema();
        }

        // Instantiate the GroupController with the database.
        $this->controller = new GroupController($this->db);
    }

    public function testCreateGroup(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/groups')
            ->withAttribute('user_id', 1)
            ->withParsedBody(['name' => 'TestGroup']);

        $response = $this->controller->createGroup(
            $request,
            (new ResponseFactory())->createResponse(),
            []
        );

        // Assuming a successful creation returns 201.
        $this->assertEquals(201, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('id', $body);
        $this->assertEquals('TestGroup', $body['name']);
    }

    public function testJoinGroup(): void
    {
        // Use a dummy user ID, e.g., 1.
        $userId = 1;

        // Create a group via the Database instance.
        // This assumes your Database class has a createGroup method.
        $groupId = $this->db->createGroup('TestGroup', $userId);

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', "/groups/$groupId/join")
            ->withAttribute('user_id', $userId);

        $response = $this->controller->joinGroup(
            $request,
            (new ResponseFactory())->createResponse(),
            ['group_id' => $groupId]
        );

        // Assuming a successful join returns 200.
        $this->assertEquals(200, $response->getStatusCode());
    }
}
