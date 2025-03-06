<?php

use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

return [
    'db_path' => getenv('DB_PATH') ?: 'db/chat.db',
    'displayErrorDetails' => (bool)(getenv('DISPLAY_ERRORS') ?: true),
    'logErrors' => (bool)(getenv('LOG_ERRORS') ?: true),
    'logErrorDetails' => (bool)(getenv('LOG_ERROR_DETAILS') ?: true),
    'logFile' => __DIR__ . '/../logs/app.log',
];