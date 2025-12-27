<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\Service\MessageService;
use App\Http\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MessageController
{
    public function __construct(private readonly MessageService $service)
    {
    }

    public function send(
        ServerRequestInterface $req,
        ResponseInterface      $res,
        array                  $args
    ): ResponseInterface
    {
        $msg = $this->service->send(
            (int)$args['id'],
            (string)($req->getParsedBody()['message'] ?? ''),
            (string)$req->getAttribute('user')
        );

        return JsonResponse::success($res, [
            'id' => $msg->id(),
            'message' => $msg->content(),
            'created_at' => $msg->createdAt()
        ], 201);
    }

    public function list(
        ServerRequestInterface $req,
        ResponseInterface      $res,
        array                  $args
    ): ResponseInterface
    {
        $messages = $this->service->list(
            (int)$args['id'],
            (string)$req->getAttribute('user')
        );

        return JsonResponse::success(
            $res,
            array_map(fn($m) => [
                'id' => $m->id(),
                'message' => $m->content(),
                'created_at' => $m->createdAt()
            ], $messages)
        );
    }
}