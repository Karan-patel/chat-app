<?php

use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

return [
    'db_path' => $_ENV['DB_PATH'] ?? ':memory:',
    'displayErrorDetails' => (bool)($_ENV['DISPLAY_ERRORS'] ?? false),
    'logErrors' => (bool)($_ENV['LOG_ERRORS'] ?? true),
    'logErrorDetails' => (bool)($_ENV['LOG_ERROR_DETAILS'] ?? false),
    'logFile' => '/logs/app.log'
];