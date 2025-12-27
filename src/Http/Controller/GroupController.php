<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\Service\GroupService;
use App\Http\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class GroupController
{
    public function __construct(private readonly GroupService $service)
    {
    }

    public function create(
        ServerRequestInterface $req,
        ResponseInterface      $res
    ): ResponseInterface
    {
        $group = $this->service->create(
            (string)($req->getParsedBody()['name'] ?? ''),
            (string)$req->getAttribute('user')
        );

        return JsonResponse::success($res, [
            'id' => $group->id(),
            'name' => $group->name()
        ], 201);
    }

    public function join(
        ServerRequestInterface $req,
        ResponseInterface      $res,
        array                  $args
    ): ResponseInterface
    {
        $this->service->join(
            (int)$args['id'],
            (string)$req->getAttribute('user')
        );

        return JsonResponse::success($res, ['message' => 'Joined group']);
    }
}