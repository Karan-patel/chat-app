<?php
declare(strict_types=1);

use App\Application\Exception\ForbiddenException;
use App\Application\Exception\NotFoundException;
use App\Application\Exception\ValidationException;
use App\Http\Controller\GroupController;
use App\Http\Controller\MessageController;
use App\Http\Middleware\UserContextMiddleware;
use App\Http\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Factory\AppFactory;


require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

$app = AppFactory::create();

/**
 * REQUIRED middleware
 */
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

/**
 * Root
 */
$app->get('/', function (
    ServerRequestInterface $request,
    ResponseInterface      $response
): ResponseInterface {
    return JsonResponse::success($response, [
        'message' => 'Hello from bunq!'
    ]);
});

/**
 * Application routes
 */
$app->group('/groups', function ($group) use ($groupService, $messageService) {

    $group->post('', [new GroupController($groupService), 'create']);
    $group->post('/{id}/join', [new GroupController($groupService), 'join']);

    $group->post('/{id}/messages', [new MessageController($messageService), 'send']);
    $group->get('/{id}/messages', [new MessageController($messageService), 'list']);

})->add(new UserContextMiddleware());

/**
 * Error handling (recommended)
 */
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$errorMiddleware->setDefaultErrorHandler(
    function (
        \Psr\Http\Message\ServerRequestInterface $request,
        \Throwable                               $exception,
        bool                                     $displayErrorDetails,
        bool                                     $logErrors,
        bool                                     $logErrorDetails
    ) use ($app) {
        $response = $app->getResponseFactory()->createResponse();

        if ($exception instanceof ValidationException) {
            return JsonResponse::error($response, $exception->getMessage(), 400);
        }

        if ($exception instanceof ForbiddenException) {
            return JsonResponse::error($response, $exception->getMessage(), 403);
        }

        if ($exception instanceof NotFoundException) {
            return JsonResponse::error($response, $exception->getMessage(), 404);
        }

        // Unknown / programmer error
        return JsonResponse::error(
            $response,
            'Internal Server Error',
            500
        );
    }
);

$app->run();