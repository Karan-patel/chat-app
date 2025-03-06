<?php
namespace App;

use DI\Container;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory as SlimAppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Throwable;

class AppFactory
{
    private $config;
    private $container;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/config.php';
        $this->container = new Container();
        $this->configureContainer();
    }

    public function create(): App
    {
        SlimAppFactory::setContainer($this->container);
        $app = SlimAppFactory::create();

        // Add JSON body parsing middleware
        $app->add(function (ServerRequestInterface $request, $handler) {
            $contentType = $request->getHeaderLine('Content-Type');
            if (stripos($contentType, 'application/json') !== false) {
                $body = (string)$request->getBody();
                $data = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $request = $request->withParsedBody($data);
                }
            }
            return $handler->handle($request);
        });

        $errorMiddleware = $app->addErrorMiddleware(
            $this->config['displayErrorDetails'],
            $this->config['logErrors'],
            $this->config['logErrorDetails']
        );
        $errorMiddleware->setDefaultErrorHandler($this->createErrorHandler());

        $registerRoutes = require __DIR__ . '/Routes.php';
        $registerRoutes($app);

        return $app;
    }

    private function configureContainer(): void
    {
        // Ensure db_path is passed correctly
        $dbPath = $this->config['db_path'];
        if (empty($dbPath)) {
            throw new \RuntimeException("Database path is empty in config");
        }

        $this->container->set(Database::class, function () {
            return new Database($this->config['db_path']);
        });

        $this->container->set(UserMiddleware::class, function ($container) {
            return new UserMiddleware($container->get(Database::class));
        });

        $this->container->set(GroupController::class, function ($container) {
            return new GroupController($container->get(Database::class));
        });

        $this->container->set(MessageController::class, function ($container) {
            return new MessageController($container->get(Database::class));
        });

        $this->container->set(Logger::class, function () {
            $logger = new Logger('app');
            $logger->pushHandler(new StreamHandler($this->config['logFile'], Logger::INFO));
            return $logger;
        });
    }

    private function createErrorHandler(): callable
    {
        $container = $this->container;

        return function (
            ServerRequestInterface $request,
            Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails
        ) use ($container) {
            $response = (new ResponseFactory())->createResponse();
            $statusCode = 500;
            $message = 'Internal Server Error';

            if ($exception instanceof BadRequestException) {
                $statusCode = 400;
                $message = $exception->getMessage();
            } elseif ($exception instanceof NotFoundException) {
                $statusCode = 404;
                $message = $exception->getMessage();
            } elseif ($exception instanceof ForbiddenException) {
                $statusCode = 403;
                $message = $exception->getMessage();
            } elseif ($exception instanceof DatabaseException) {
                $statusCode = 500;
                $message = $displayErrorDetails ? $exception->getMessage() : 'Database error occurred';
            } elseif ($exception instanceof HttpNotFoundException) {
                $statusCode = 404;
                $message = 'Route not found';
            }

            if ($logErrors) {
                $logger = $container->get(Logger::class);
                $logger->error("[$statusCode] " . $exception->getMessage(), [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $logErrorDetails ? $exception->getTraceAsString() : null
                ]);
            }

            $response->getBody()->write(json_encode(['error' => $message]));
            return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
        };
    }
}