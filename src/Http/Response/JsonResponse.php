<?php
declare(strict_types=1);

namespace App\Http\Response;

use Psr\Http\Message\ResponseInterface;

final class JsonResponse
{
    public static function success(
        ResponseInterface $res,
        mixed             $data,
        int               $status = 200
    ): ResponseInterface
    {
        $res->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $data
        ], JSON_THROW_ON_ERROR));

        return $res
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    public static function error(
        ResponseInterface $res,
        string            $message,
        int               $status
    ): ResponseInterface
    {
        $res->getBody()->write(json_encode([
            'status' => 'error',
            'error' => ['message' => $message]
        ], JSON_THROW_ON_ERROR));

        return $res
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}