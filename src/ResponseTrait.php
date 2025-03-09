<?php

namespace App;

use Psr\Http\Message\ResponseInterface as Response;

trait ResponseTrait
{
    private function jsonResponse(Response $response, $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}