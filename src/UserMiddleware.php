<?php
namespace App;
require_once __DIR__ . '/../src/Exceptions.php';

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

class UserMiddleware
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $username = $request->getHeaderLine('X-Username');
        if (empty($username)) {
            throw new BadRequestException('Username header (X-Username) is missing', 400);
        }

        try {
            $userId = $this->db->getUserIdByUsername($username);
            if ($userId === null) {
                $userId = $this->db->createUser($username);
            }
        } catch (\PDOException $e) {
            throw new DatabaseException('Failed to process user: ' . $e->getMessage(), 500, $e);
        }

        $request = $request->withAttribute('user_id', $userId);
        return $handler->handle($request);
    }
}