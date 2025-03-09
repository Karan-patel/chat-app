<?php
require __DIR__ . '/../vendor/autoload.php';

use App\AppFactory;

try {
    $appFactory = new AppFactory();
    $app = $appFactory->create();
    $app->run();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Application failed to start: ' . $e->getMessage()]);
    exit;
}