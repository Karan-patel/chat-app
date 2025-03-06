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

class EndToEndTest extends TestCase
{
    private $db;
    private $middleware;
    private $messageController;
    private $groupController;

    protected function setUp(): void
    {
        $this->dbFile = __DIR__ . '/../test_chat.db';
        if (file_exists($this->dbFile)) {
            unlink($this->dbFile);
        }

        $envContent = "DB_PATH=test_chat.db\nDISPLAY_ERRORS=true\nLOG_ERRORS=true\nLOG_ERROR_DETAILS=true";
        file_put_contents(__DIR__ . '/../.env', $envContent);

        $config = require __DIR__ . '/../config/config.php';
        $this->db = new Database($config['db_path']); // Use file-based db_path from config
        $this->middleware = new UserMiddleware($this->db);
        $this->messageController = new MessageController($this->db);
        $this->groupController = new GroupController($this->db);
    }

    protected function tearDown(): void
    {
        unset($this->db, $this->middleware, $this->messageController, $this->groupController);
        if (file_exists($this->dbFile)) {
            unlink($this->dbFile);
        }
        if (file_exists(__DIR__ . '/../.env')) {
            unlink(__DIR__ . '/../.env');
        }
    }

    public function testFullFlowMissingUsernameHeader(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/groups');
        $handler = $this->createMock(RequestHandlerInterface::class);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Username header (X-Username) is missing');
        $this->expectExceptionCode(400);

        $this->middleware->__invoke($request, $handler);
    }

    public function testCreateUserAndGroupThenJoinAndSendMessage(): void
    {
        // Step 1: Middleware creates a new user and group
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['name' => 'Test Group']);
        $response = (new ResponseFactory())->createResponse();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($response) {
                $this->assertEquals(1, $req->getAttribute('user_id')); // First user ID
                return $this->groupController->createGroup($req, $response, []);
            });

        $result = $this->middleware->__invoke($request, $handler);
        $this->assertEquals(201, $result->getStatusCode());
        $this->assertEquals('{"id":1,"name":"Test Group"}', (string)$result->getBody());

        // Step 2: Join the group
        $joinRequest = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/join')
            ->withHeader('X-Username', 'alice');
        $joinResponse = (new ResponseFactory())->createResponse();

        $joinHandler = $this->createMock(RequestHandlerInterface::class);
        $joinHandler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($joinResponse) {
                $this->assertEquals(1, $req->getAttribute('user_id'));
                return $this->groupController->joinGroup($req, $joinResponse, ['group_id' => '1']);
            });

        $joinResult = $this->middleware->__invoke($joinRequest, $joinHandler);
        $this->assertEquals(200, $joinResult->getStatusCode());
        $this->assertEquals('{"status":"joined"}', (string)$joinResult->getBody());

        // Step 3: Send a message
        $msgRequest = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/messages')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['message' => 'Hello World']);
        $msgResponse = (new ResponseFactory())->createResponse();

        $msgHandler = $this->createMock(RequestHandlerInterface::class);
        $msgHandler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($msgResponse) {
                $this->assertEquals(1, $req->getAttribute('user_id'));
                return $this->messageController->sendMessage($req, $msgResponse, ['group_id' => '1']);
            });

        $msgResult = $this->middleware->__invoke($msgRequest, $msgHandler);
        $this->assertEquals(201, $msgResult->getStatusCode());
        $this->assertStringContainsString('"message":"Hello World"', (string)$msgResult->getBody());

        // Step 4: List messages
        $listRequest = (new ServerRequestFactory())->createServerRequest('GET', '/groups/1/messages')
            ->withHeader('X-Username', 'alice');
        $listResponse = (new ResponseFactory())->createResponse();

        $listHandler = $this->createMock(RequestHandlerInterface::class);
        $listHandler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($listResponse) {
                $this->assertEquals(1, $req->getAttribute('user_id'));
                return $this->messageController->listMessages($req, $listResponse, ['group_id' => '1']);
            });

        $listResult = $this->middleware->__invoke($listRequest, $listHandler);
        $this->assertEquals(200, $listResult->getStatusCode());
        $this->assertStringContainsString('"message":"Hello World"', (string)$listResult->getBody());
    }

    public function testSendMessageToNonExistentGroup(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups/999/messages')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['message' => 'Hi']);
        $response = (new ResponseFactory())->createResponse();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($response) {
                $this->assertEquals(1, $req->getAttribute('user_id')); // User created
                try {
                    return $this->messageController->sendMessage($req, $response, ['group_id' => '999']);
                } catch (NotFoundException $e) {
                    $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
                    return $response->withStatus($e->getCode())->withHeader('Content-Type', 'application/json');
                }
            });

        $result = $this->middleware->__invoke($request, $handler);
        $this->assertEquals(404, $result->getStatusCode());
        $this->assertEquals('{"error":"Group not found"}', (string)$result->getBody());
    }

    public function testSendMessageUserNotInGroup(): void
    {
        // Create a group first
        $groupRequest = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['name' => 'Test Group']);
        $groupResponse = (new ResponseFactory())->createResponse();

        $groupHandler = $this->createMock(RequestHandlerInterface::class);
        $groupHandler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($groupResponse) {
                return $this->groupController->createGroup($req, $groupResponse, []);
            });

        $this->middleware->__invoke($groupRequest, $groupHandler);

        // Try sending a message without joining
        $msgRequest = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/messages')
            ->withHeader('X-Username', 'bob')
            ->withParsedBody(['message' => 'Hi']);
        $msgResponse = (new ResponseFactory())->createResponse();

        $msgHandler = $this->createMock(RequestHandlerInterface::class);
        $msgHandler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($msgResponse) {
                $this->assertEquals(2, $req->getAttribute('user_id')); // Bob’s ID
                try {
                    return $this->messageController->sendMessage($req, $msgResponse, ['group_id' => '1']);
                } catch (ForbiddenException $e) {
                    $msgResponse->getBody()->write(json_encode(['error' => $e->getMessage()]));
                    return $msgResponse->withStatus($e->getCode())->withHeader('Content-Type', 'application/json');
                }
            });

        $result = $this->middleware->__invoke($msgRequest, $msgHandler);
        $this->assertEquals(403, $result->getStatusCode());
        $this->assertEquals('{"error":"User must join the group to send messages"}', (string)$result->getBody());
    }

    public function testCreateGroupInvalidName(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['name' => '   ']);
        $response = (new ResponseFactory())->createResponse();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($response) {
                try {
                    return $this->groupController->createGroup($req, $response, []);
                } catch (BadRequestException $e) {
                    $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
                    return $response->withStatus($e->getCode())->withHeader('Content-Type', 'application/json');
                }
            });

        $result = $this->middleware->__invoke($request, $handler);
        $this->assertEquals(400, $result->getStatusCode());
        $this->assertEquals('{"error":"Group name is required and must be a non-empty string"}', (string)$result->getBody());
    }

    public function testJoinNonExistentGroup(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/groups/999/join')
            ->withHeader('X-Username', 'alice');
        $response = (new ResponseFactory())->createResponse();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($response) {
                try {
                    return $this->groupController->joinGroup($req, $response, ['group_id' => '999']);
                } catch (NotFoundException $e) {
                    $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
                    return $response->withStatus($e->getCode())->withHeader('Content-Type', 'application/json');
                }
            });

        $result = $this->middleware->__invoke($request, $handler);
        $this->assertEquals(404, $result->getStatusCode());
        $this->assertEquals('{"error":"Group not found"}', (string)$result->getBody());
    }

    public function testMultipleUsersJoinAndChatInGroup(): void
    {
        // Step 1: Alice creates a group
        $groupRequest = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['name' => 'Chat Room']);
        $groupResponse = (new ResponseFactory())->createResponse();
        $groupHandler = $this->createMock(RequestHandlerInterface::class);
        $groupHandler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($groupResponse) {
                $this->assertEquals(1, $req->getAttribute('user_id'));
                return $this->groupController->createGroup($req, $groupResponse, []);
            });

        $groupResult = $this->middleware->__invoke($groupRequest, $groupHandler);
        $this->assertEquals(201, $groupResult->getStatusCode());
        $this->assertEquals('{"id":1,"name":"Chat Room"}', (string)$groupResult->getBody());

        // Step 2: Alice joins the group
        $aliceJoinRequest = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/join')
            ->withHeader('X-Username', 'alice');
        $aliceJoinResponse = (new ResponseFactory())->createResponse();
        $aliceJoinHandler = $this->createMock(RequestHandlerInterface::class);
        $aliceJoinHandler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($aliceJoinResponse) {
                $this->assertEquals(1, $req->getAttribute('user_id'));
                return $this->groupController->joinGroup($req, $aliceJoinResponse, ['group_id' => '1']);
            });

        $aliceJoinResult = $this->middleware->__invoke($aliceJoinRequest, $aliceJoinHandler);
        $this->assertEquals(200, $aliceJoinResult->getStatusCode());

        // Step 3: Bob joins the group
        $bobJoinRequest = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/join')
            ->withHeader('X-Username', 'bob');
        $bobJoinResponse = (new ResponseFactory())->createResponse();
        $bobJoinHandler = $this->createMock(RequestHandlerInterface::class);
        $bobJoinHandler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($bobJoinResponse) {
                $this->assertEquals(2, $req->getAttribute('user_id'));
                return $this->groupController->joinGroup($req, $bobJoinResponse, ['group_id' => '1']);
            });

        $bobJoinResult = $this->middleware->__invoke($bobJoinRequest, $bobJoinHandler);
        $this->assertEquals(200, $bobJoinResult->getStatusCode());

        // Step 4: Alice sends a message
        $aliceMsgRequest = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/messages')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['message' => 'Hi Bob!']);
        $aliceMsgResponse = (new ResponseFactory())->createResponse();
        $aliceMsgHandler = $this->createMock(RequestHandlerInterface::class);
        $aliceMsgHandler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($aliceMsgResponse) {
                $this->assertEquals(1, $req->getAttribute('user_id'));
                return $this->messageController->sendMessage($req, $aliceMsgResponse, ['group_id' => '1']);
            });

        $aliceMsgResult = $this->middleware->__invoke($aliceMsgRequest, $aliceMsgHandler);
        $this->assertEquals(201, $aliceMsgResult->getStatusCode());
        $this->assertStringContainsString('"message":"Hi Bob!"', (string)$aliceMsgResult->getBody());

        // Step 5: Bob sends a message
        $bobMsgRequest = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/messages')
            ->withHeader('X-Username', 'bob')
            ->withParsedBody(['message' => 'Hello Alice!']);
        $bobMsgResponse = (new ResponseFactory())->createResponse();
        $bobMsgHandler = $this->createMock(RequestHandlerInterface::class);
        $bobMsgHandler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($bobMsgResponse) {
                $this->assertEquals(2, $req->getAttribute('user_id'));
                return $this->messageController->sendMessage($req, $bobMsgResponse, ['group_id' => '1']);
            });

        $bobMsgResult = $this->middleware->__invoke($bobMsgRequest, $bobMsgHandler);
        $this->assertEquals(201, $bobMsgResult->getStatusCode());
        $this->assertStringContainsString('"message":"Hello Alice!"', (string)$bobMsgResult->getBody());

        // Step 6: List messages
        $listRequest = (new ServerRequestFactory())->createServerRequest('GET', '/groups/1/messages')
            ->withHeader('X-Username', 'alice');
        $listResponse = (new ResponseFactory())->createResponse();
        $listHandler = $this->createMock(RequestHandlerInterface::class);
        $listHandler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($listResponse) {
                $this->assertEquals(1, $req->getAttribute('user_id'));
                return $this->messageController->listMessages($req, $listResponse, ['group_id' => '1']);
            });

        $listResult = $this->middleware->__invoke($listRequest, $listHandler);
        $this->assertEquals(200, $listResult->getStatusCode());
        $this->assertStringContainsString('"message":"Hi Bob!"', (string)$listResult->getBody());
        $this->assertStringContainsString('"message":"Hello Alice!"', (string)$listResult->getBody());
    }

    public function testMultipleGroupsAndMessages(): void
    {
        // Step 1: Alice creates Group 1
        $group1Request = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['name' => 'Group 1']);
        $group1Response = (new ResponseFactory())->createResponse();
        $group1Handler = $this->createMock(RequestHandlerInterface::class);
        $group1Handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($group1Response) {
                $this->assertEquals(1, $req->getAttribute('user_id'));
                return $this->groupController->createGroup($req, $group1Response, []);
            });

        $group1Result = $this->middleware->__invoke($group1Request, $group1Handler);
        $this->assertEquals(201, $group1Result->getStatusCode());

        // Step 2: Alice creates Group 2
        $group2Request = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['name' => 'Group 2']);
        $group2Response = (new ResponseFactory())->createResponse();
        $group2Handler = $this->createMock(RequestHandlerInterface::class);
        $group2Handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($group2Response) {
                $this->assertEquals(1, $req->getAttribute('user_id'));
                return $this->groupController->createGroup($req, $group2Response, []);
            });

        $group2Result = $this->middleware->__invoke($group2Request, $group2Handler);
        $this->assertEquals(201, $group2Result->getStatusCode());

        // Step 3: Alice joins both groups
        $join1Request = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/join')
            ->withHeader('X-Username', 'alice');
        $join1Response = (new ResponseFactory())->createResponse();
        $join1Handler = $this->createMock(RequestHandlerInterface::class);
        $join1Handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($join1Response) {
                return $this->groupController->joinGroup($req, $join1Response, ['group_id' => '1']);
            });

        $this->middleware->__invoke($join1Request, $join1Handler);

        $join2Request = (new ServerRequestFactory())->createServerRequest('POST', '/groups/2/join')
            ->withHeader('X-Username', 'alice');
        $join2Response = (new ResponseFactory())->createResponse();
        $join2Handler = $this->createMock(RequestHandlerInterface::class);
        $join2Handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($join2Response) {
                return $this->groupController->joinGroup($req, $join2Response, ['group_id' => '2']);
            });

        $this->middleware->__invoke($join2Request, $join2Handler);

        // Step 4: Alice sends messages to both groups
        $msg1Request = (new ServerRequestFactory())->createServerRequest('POST', '/groups/1/messages')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['message' => 'Group 1 chat']);
        $msg1Response = (new ResponseFactory())->createResponse();
        $msg1Handler = $this->createMock(RequestHandlerInterface::class);
        $msg1Handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($msg1Response) {
                return $this->messageController->sendMessage($req, $msg1Response, ['group_id' => '1']);
            });

        $msg1Result = $this->middleware->__invoke($msg1Request, $msg1Handler);
        $this->assertEquals(201, $msg1Result->getStatusCode());

        $msg2Request = (new ServerRequestFactory())->createServerRequest('POST', '/groups/2/messages')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['message' => 'Group 2 chat']);
        $msg2Response = (new ResponseFactory())->createResponse();
        $msg2Handler = $this->createMock(RequestHandlerInterface::class);
        $msg2Handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($msg2Response) {
                return $this->messageController->sendMessage($req, $msg2Response, ['group_id' => '2']);
            });

        $msg2Result = $this->middleware->__invoke($msg2Request, $msg2Handler);
        $this->assertEquals(201, $msg2Result->getStatusCode());

        // Step 5: List messages for both groups
        $list1Request = (new ServerRequestFactory())->createServerRequest('GET', '/groups/1/messages')
            ->withHeader('X-Username', 'alice');
        $list1Response = (new ResponseFactory())->createResponse();
        $list1Handler = $this->createMock(RequestHandlerInterface::class);
        $list1Handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($list1Response) {
                return $this->messageController->listMessages($req, $list1Response, ['group_id' => '1']);
            });

        $list1Result = $this->middleware->__invoke($list1Request, $list1Handler);
        $this->assertEquals(200, $list1Result->getStatusCode());
        $this->assertStringContainsString('"message":"Group 1 chat"', (string)$list1Result->getBody());

        $list2Request = (new ServerRequestFactory())->createServerRequest('GET', '/groups/2/messages')
            ->withHeader('X-Username', 'alice');
        $list2Response = (new ResponseFactory())->createResponse();
        $list2Handler = $this->createMock(RequestHandlerInterface::class);
        $list2Handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($list2Response) {
                return $this->messageController->listMessages($req, $list2Response, ['group_id' => '2']);
            });

        $list2Result = $this->middleware->__invoke($list2Request, $list2Handler);
        $this->assertEquals(200, $list2Result->getStatusCode());
        $this->assertStringContainsString('"message":"Group 2 chat"', (string)$list2Result->getBody());
    }

    public function testListAllGroupsAfterCreation(): void
    {
        // Step 1: Create multiple groups
        $group1Request = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withHeader('X-Username', 'alice')
            ->withParsedBody(['name' => 'Group A']);
        $group1Response = (new ResponseFactory())->createResponse();
        $group1Handler = $this->createMock(RequestHandlerInterface::class);
        $group1Handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($group1Response) {
                $this->assertEquals(1, $req->getAttribute('user_id'));
                return $this->groupController->createGroup($req, $group1Response, []);
            });

        $this->middleware->__invoke($group1Request, $group1Handler);

        $group2Request = (new ServerRequestFactory())->createServerRequest('POST', '/groups')
            ->withHeader('X-Username', 'bob')
            ->withParsedBody(['name' => 'Group B']);
        $group2Response = (new ResponseFactory())->createResponse();
        $group2Handler = $this->createMock(RequestHandlerInterface::class);
        $group2Handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($group2Response) {
                $this->assertEquals(2, $req->getAttribute('user_id'));
                return $this->groupController->createGroup($req, $group2Response, []);
            });

        $this->middleware->__invoke($group2Request, $group2Handler);

        // Step 2: List all groups
        $listRequest = (new ServerRequestFactory())->createServerRequest('GET', '/groups')
            ->withHeader('X-Username', 'alice');
        $listResponse = (new ResponseFactory())->createResponse();
        $listHandler = $this->createMock(RequestHandlerInterface::class);
        $listHandler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (RequestInterface $req) use ($listResponse) {
                $this->assertEquals(1, $req->getAttribute('user_id'));
                return $this->groupController->listGroups($req, $listResponse, []);
            });

        $listResult = $this->middleware->__invoke($listRequest, $listHandler);
        $this->assertEquals(200, $listResult->getStatusCode());
        $this->assertStringContainsString('"name":"Group A"', (string)$listResult->getBody());
        $this->assertStringContainsString('"name":"Group B"', (string)$listResult->getBody());
    }
}