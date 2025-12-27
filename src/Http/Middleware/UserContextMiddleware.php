<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

final class UserContextMiddleware
{
    public function __invoke(ServerRequestInterface $request, $handler)
    {
        $user = $request->getHeaderLine('X-User-Id');

        if ($user === '') {
            $res = new Response(400);
            $res->getBody()->write(json_encode(['error' => 'X-User-Id required']));
            return $res->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request->withAttribute('user', $user));
    }
}