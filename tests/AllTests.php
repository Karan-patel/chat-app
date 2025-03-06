<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Exceptions.php';

use App\BadRequestException;
use App\Database;
use App\ForbiddenException;
use App\GroupController;
use App\MessageController;
use App\NotFoundException;
use App\UserMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as RequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class AllTests extends TestCase
{
    private $db;

    protected function setUp(): void
    {
        $this->db = $this->createMock(Database::class);
    }

    // --- UserMiddleware Tests ---

    public function testUserMiddlewareMissingUsernameHeader(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $middleware = new UserMiddleware($this->db);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Username header (X-Username) is missing');
        $this->expectExceptionCode(400);

        $middleware->__invoke($request, $handler);
    }

    public function testUserMiddlewareExistingUser(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/')
            ->withHeader('X-Username', 'alice');
        $response = (new ResponseFactory())->createResponse();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $middleware = new UserMiddleware($this->db);

        $this->db->expects($this->once())
            ->method('getUserIdByUsername')
            ->with('alice')
            ->willReturn(1);
        $this->db->expects($this->never())->method('createUser');
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (RequestInterface $req) {
                return $req->getAttribute('user_id') === 1;
            }))
            ->willReturn($response);

        $result = $middleware->__invoke($request, $handler);
        $this->assertEquals($response, $result);
    }

    public function testUserMiddlewareNewUser(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/')
            ->withHeader('X-Username', 'bob');
        $response = (new ResponseFactory())->createResponse();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $middleware = new UserMiddleware($this->db);

        $this->db->expects($this->once())
            ->method('getUserIdByUsername')
            ->with('bob')
            ->willReturn(null);
        $this->db->expects($this->once())
            ->method('createUser')
            ->with('bob')
            ->willReturn(2);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (RequestInterface $req) {
                return $req->getAttribute('user_id') === 2;
            }))
            ->willReturn($response);

        $result = $middleware->__invoke($request, $handler);
        $this->assertEquals($response, $result);
    }

    // --- MessageController Tests ---

    public function testSendMessageGroupNotFound(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups/999/messages')
            ->withAttribute('user_id', 1)
            ->withParsedBody(['message' => 'Hello']);
        $response = (new ResponseFactory())->createResponse();
        $controller = new MessageController($this->db);

        $this->db->expects($this->once())
            ->method('groupExists')
            ->with(999)
            ->willReturn(false);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Group not found');
        $this->expectExceptionCode(404);

        $controller->sendMessage($request, $response, ['group_id' => '999']);
    }

    public function testSendMessageUserNotInGroup(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/messages')
            ->withAttribute('user_id', 1)
            ->withParsedBody(['message' => 'Hello']);
        $response = (new ResponseFactory())->createResponse();
        $controller = new MessageController($this->db);

        $this->db->expects($this->once())
            ->method('groupExists')
            ->with(1)
            ->willReturn(true);
        $this->db->expects($this->once())
            ->method('isUserInGroup')
            ->with(1, 1)
            ->willReturn(false);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('User must join the group to send messages');
        $this->expectExceptionCode(403);

        $controller->sendMessage($request, $response, ['group_id' => '1']);
    }

    public function testSendMessageMissingMessage(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/messages')
            ->withAttribute('user_id', 1)
            ->withParsedBody([]);
        $response = (new ResponseFactory())->createResponse();
        $controller = new MessageController($this->db);

        $this->db->expects($this->once())
            ->method('groupExists')
            ->with(1)
            ->willReturn(true);
        $this->db->expects($this->once())
            ->method('isUserInGroup')
            ->with(1, 1)
            ->willReturn(true);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Message is required and must be a non-empty string');
        $this->expectExceptionCode(400);

        $controller->sendMessage($request, $response, ['group_id' => '1']);
    }

    public function testSendMessageEmptyMessage(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/messages')
            ->withAttribute('user_id', 1)
            ->withParsedBody(['message' => '   ']);
        $response = (new ResponseFactory())->createResponse();
        $controller = new MessageController($this->db);

        $this->db->expects($this->once())
            ->method('groupExists')
            ->with(1)
            ->willReturn(true);
        $this->db->expects($this->once())
            ->method('isUserInGroup')
            ->with(1, 1)
            ->willReturn(true);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Message is required and must be a non-empty string');
        $this->expectExceptionCode(400);

        $controller->sendMessage($request, $response, ['group_id' => '1']);
    }

    public function testSendMessageSuccess(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/messages')
            ->withAttribute('user_id', 1)
            ->withParsedBody(['message' => 'Hello']);
        $response = (new ResponseFactory())->createResponse();
        $controller = new MessageController($this->db);

        $this->db->expects($this->once())
            ->method('groupExists')
            ->with(1)
            ->willReturn(true);
        $this->db->expects($this->once())
            ->method('isUserInGroup')
            ->with(1, 1)
            ->willReturn(true);
        $this->db->expects($this->once())
            ->method('sendMessage')
            ->with(1, 1, 'Hello')
            ->willReturn(100);
        $this->db->expects($this->once())
            ->method('getMessageById')
            ->with(100)
            ->willReturn(['id' => 100, 'group_id' => 1, 'user_id' => 1, 'message' => 'Hello']);

        $result = $controller->sendMessage($request, $response, ['group_id' => '1']);
        $this->assertEquals(201, $result->getStatusCode());
        $this->assertEquals('{"id":100,"group_id":1,"user_id":1,"message":"Hello"}', (string)$result->getBody());
    }

    public function testListMessagesGroupNotFound(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/groups/999/messages');
        $response = (new ResponseFactory())->createResponse();
        $controller = new MessageController($this->db);

        $this->db->expects($this->once())
            ->method('groupExists')
            ->with(999)
            ->willReturn(false);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Group not found');
        $this->expectExceptionCode(404);

        $controller->listMessages($request, $response, ['group_id' => '999']);
    }

    public function testListMessagesSuccess(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/groups/1/messages');
        $response = (new ResponseFactory())->createResponse();
        $controller = new MessageController($this->db);

        $this->db->expects($this->once())
            ->method('groupExists')
            ->with(1)
            ->willReturn(true);
        $this->db->expects($this->once())
            ->method('getMessagesByGroup')
            ->with(1)
            ->willReturn([['id' => 1, 'message' => 'Hi']]);

        $result = $controller->listMessages($request, $response, ['group_id' => '1']);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('[{"id":1,"message":"Hi"}]', (string)$result->getBody());
    }

    // --- GroupController Tests ---

    public function testListGroupsSuccess(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/groups');
        $response = (new ResponseFactory())->createResponse();
        $controller = new GroupController($this->db);

        $this->db->expects($this->once())
            ->method('getAllGroups')
            ->willReturn([['id' => 1, 'name' => 'Group1']]);

        $result = $controller->listGroups($request, $response, []);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('[{"id":1,"name":"Group1"}]', (string)$result->getBody());
    }

    public function testCreateGroupMissingName(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withAttribute('user_id', 1)
            ->withParsedBody([]);
        $response = (new ResponseFactory())->createResponse();
        $controller = new GroupController($this->db);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Group name is required and must be a non-empty string');
        $this->expectExceptionCode(400);

        $controller->createGroup($request, $response, []);
    }

    public function testCreateGroupEmptyName(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withAttribute('user_id', 1)
            ->withParsedBody(['name' => '   ']);
        $response = (new ResponseFactory())->createResponse();
        $controller = new GroupController($this->db);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Group name is required and must be a non-empty string');
        $this->expectExceptionCode(400);

        $controller->createGroup($request, $response, []);
    }

    public function testCreateGroupSuccess(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withAttribute('user_id', 1)
            ->withParsedBody(['name' => 'Test Group']);
        $response = (new ResponseFactory())->createResponse();
        $controller = new GroupController($this->db);

        $this->db->expects($this->once())
            ->method('createGroup')
            ->with('Test Group', 1)
            ->willReturn(10);

        $result = $controller->createGroup($request, $response, []);
        $this->assertEquals(201, $result->getStatusCode());
        $this->assertEquals('{"id":10,"name":"Test Group"}', (string)$result->getBody());
    }

    public function testJoinGroupNotFound(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups/999/join')
            ->withAttribute('user_id', 1);
        $response = (new ResponseFactory())->createResponse();
        $controller = new GroupController($this->db);

        $this->db->expects($this->once())
            ->method('groupExists')
            ->with(999)
            ->willReturn(false);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Group not found');
        $this->expectExceptionCode(404);

        $controller->joinGroup($request, $response, ['group_id' => '999']);
    }

    public function testJoinGroupSuccess(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/join')
            ->withAttribute('user_id', 1);
        $response = (new ResponseFactory())->createResponse();
        $controller = new GroupController($this->db);

        $this->db->expects($this->once())
            ->method('groupExists')
            ->with(1)
            ->willReturn(true);
        $this->db->expects($this->once())
            ->method('joinGroup')
            ->with(1, 1);

        $result = $controller->joinGroup($request, $response, ['group_id' => '1']);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('{"status":"joined"}', (string)$result->getBody());
    }
}