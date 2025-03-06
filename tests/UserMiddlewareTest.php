<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Exceptions.php';

use App\BadRequestException;
use App\Database;
use App\UserMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as RequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class UserMiddlewareTest extends TestCase
{
    private $middleware;
    private $db;

    protected function setUp(): void
    {
        // Mock the Database dependency
        $this->db = $this->createMock(Database::class);
        $this->middleware = new UserMiddleware($this->db);
    }

    public function testMissingUsernameHeader(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $handler = $this->createMock(RequestHandlerInterface::class);

        // Expect an exception for missing X-Username
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Username header (X-Username) is missing');
        $this->expectExceptionCode(400);

        $this->middleware->__invoke($request, $handler);
    }

    public function testValidUsernameHeaderExistingUser(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/')
            ->withHeader('X-Username', 'alice');

        $responseFactory = new ResponseFactory();
        $expectedResponse = $responseFactory->createResponse();

        // Mock Database: user exists
        $this->db->expects($this->once())
            ->method('getUserIdByUsername')
            ->with('alice')
            ->willReturn(42); // Existing user ID

        $this->db->expects($this->never())
            ->method('createUser'); // Should not create a new user

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (RequestInterface $req) {
                return $req->getAttribute('user_id') === 42 &&
                    $req->getHeaderLine('X-Username') === 'alice';
            }))
            ->willReturn($expectedResponse);

        $response = $this->middleware->__invoke($request, $handler);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals($expectedResponse, $response);
    }

    public function testValidUsernameHeaderNewUser(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/')
            ->withHeader('X-Username', 'bob');

        $responseFactory = new ResponseFactory();
        $expectedResponse = $responseFactory->createResponse();

        // Mock Database: user doesn’t exist, then created
        $this->db->expects($this->once())
            ->method('getUserIdByUsername')
            ->with('bob')
            ->willReturn(null); // User not found

        $this->db->expects($this->once())
            ->method('createUser')
            ->with('bob')
            ->willReturn(43); // New user ID

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (RequestInterface $req) {
                return $req->getAttribute('user_id') === 43 &&
                    $req->getHeaderLine('X-Username') === 'bob';
            }))
            ->willReturn($expectedResponse);

        $response = $this->middleware->__invoke($request, $handler);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals($expectedResponse, $response);
    }
}