<?php

use App\GroupController;
use App\MessageController;
use App\UserMiddleware;
use Slim\App;

return function (App $app) {

    // Default root route '/'
    $app->get('/', function ($request, $response) {
        $response->getBody()->write(json_encode(['message' => 'Hello from bunq!']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Group all authenticated routes under /groups
    $app->group('/groups', function ($group) {
        $group->get('', [GroupController::class, 'listGroups']);
        $group->post('', [GroupController::class, 'createGroup']);
        $group->post('/{group_id}/join', [GroupController::class, 'joinGroup']);
        $group->post('/{group_id}/messages', [MessageController::class, 'sendMessage']);
        $group->get('/{group_id}/messages', [MessageController::class, 'listMessages']);
    })->add(UserMiddleware::class);
};