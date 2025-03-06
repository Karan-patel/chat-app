<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Exceptions.php';

use App\AppFactory;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

class FullAppEndToEndTest extends TestCase
{
    private $app;

    protected function setUp(): void
    {
        $appFactory = new AppFactory();
        $this->app = $appFactory->create();
    }

    public function testListGroupsPositive(): void
    {
        $createRequest = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['name' => 'Test Group']);
        $this->app->handle($createRequest);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/groups')
            ->withHeader('X-Username', 'alice');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertCount(1, $body);
        $this->assertEquals('Test Group', $body[0]['name']);
    }

    public function testListGroupsRainyNoUsername(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/groups');
        $response = $this->app->handle($request); // Expect middleware to return a response, not throw

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('{"error":"Username header (X-Username) is missing"}', (string)$response->getBody());
    }

    public function testCreateGroupPositive(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['name' => 'New Group']);
        $response = $this->app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals(1, $body['id']);
        $this->assertEquals('New Group', $body['name']);
    }

    public function testCreateGroupRainyMissingName(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody([]);
        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('{"error":"Group name is required and must be a non-empty string"}', (string)$response->getBody());
    }

    public function testJoinGroupPositive(): void
    {
        $createRequest = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['name' => 'Test Group']);
        $this->app->handle($createRequest);

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/join')
            ->withHeader('X-Username', 'bob');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"status":"joined"}', (string)$response->getBody());
    }

    public function testJoinGroupRainyNonExistentGroup(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups/999/join')
            ->withHeader('X-Username', 'alice');
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('{"error":"Group not found"}', (string)$response->getBody());
    }

    public function testSendMessagePositive(): void
    {
        $createRequest = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['name' => 'Test Group']);
        $this->app->handle($createRequest);

        $joinRequest = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/join')
            ->withHeader('X-Username', 'alice');
        $this->app->handle($joinRequest);

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/messages')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['message' => 'Hello!']);
        $response = $this->app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals(1, $body['id']);
        $this->assertEquals('Hello!', $body['message']);
    }

    public function testSendMessageRainyNotJoined(): void
    {
        $createRequest = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['name' => 'Test Group']);
        $this->app->handle($createRequest);

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/messages')
            ->withHeader('X-Username', 'bob')
            ->withParsedBody(['message' => 'Hi!']);
        $response = $this->app->handle($request);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('{"error":"User must join the group to send messages"}', (string)$response->getBody());
    }

    public function testListMessagesPositive(): void
    {
        $createRequest = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['name' => 'Test Group']);
        $this->app->handle($createRequest);

        $joinRequest = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/join')
            ->withHeader('X-Username', 'alice');
        $this->app->handle($joinRequest);

        $msgRequest = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/messages')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['message' => 'Hello!']);
        $this->app->handle($msgRequest);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/groups/1/messages')
            ->withHeader('X-Username', 'alice');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertCount(1, $body);
        $this->assertEquals('Hello!', $body[0]['message']);
    }

    public function testListMessagesRainyNonExistentGroup(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/groups/999/messages')
            ->withHeader('X-Username', 'alice');
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('{"error":"Group not found"}', (string)$response->getBody());
    }
}