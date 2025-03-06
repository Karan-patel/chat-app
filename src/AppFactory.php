<?php
namespace App;

use DI\Container;
use Dotenv\Dotenv;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Phar;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
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
        // Get project root using the standard function
        $projectRoot = getProjectRoot();

        // Load .env from project root
        $dotenv = Dotenv::createImmutable($projectRoot);
        $dotenv->load();

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

/**
 * Determines the project root directory in a robust, environment-agnostic manner.
 *
 * Resolves the project root based on the execution context:
 * - Local: Uses the parent directory of src/ (project root) or the script's directory.
 * - Phar: Uses the directory containing the .phar file or the script's directory.
 * - Docker: Uses the working directory (e.g., /var/www) or /app if detected.
 *
 * @return string The resolved project root directory.
 * @throws RuntimeException If the project root cannot be determined or is not a valid directory.
 */
function getProjectRoot(): string
{
    // Start with the script's directory (works for local and .phar)
    $scriptPath = realpath($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['argv'][0] ?? __FILE__);
    $scriptDir = $scriptPath ? dirname($scriptPath) : null;

    // 1. Phar context: Prefer .phar location if available
    if (defined('__PHP_PHAR__') && Phar::running(false) !== '') {
        $pharPath = Phar::running(false);
        $root = dirname($pharPath);
        if ($root && is_dir($root)) {
            return $root;
        }
        if ($scriptDir && is_dir($scriptDir)) {
            return $scriptDir;
        }
    }

    // 2. Local context: Try parent of src/ first, then script directory
    $localRoot = realpath(__DIR__ . '/..');
    if ($localRoot && is_dir($localRoot)) {
        return $localRoot;
    }
    if ($scriptDir && is_dir($scriptDir)) {
        return $scriptDir;
    }

    // 3. Docker context: Check for Docker and use working directory
    if (file_exists('/.dockerenv') || getenv('DOCKER_ENV') !== false) {
        $dockerRoot = '/app';
        if (is_dir($dockerRoot)) {
            return $dockerRoot;
        }
        $cwd = getcwd();
        if ($cwd && is_dir($cwd)) {
            return $cwd;
        }
    }

    // Final fallback: Current working directory
    $cwd = getcwd();
    if ($cwd && is_dir($cwd)) {
        return $cwd;
    }

    throw new RuntimeException(
        'Unable to determine project root directory. ' .
        'Phar path: ' . (Phar::running(false) ?: 'not set') . ', ' .
        'Script path: ' . ($scriptPath ?: 'not set') . ', ' .
        'Local root: ' . ($localRoot ?: 'not resolved') . ', ' .
        'CWD: ' . (getcwd() ?: 'not set')
    );
}