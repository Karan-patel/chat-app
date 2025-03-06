<?php
require __DIR__ . '/../vendor/autoload.php';

use App\AppFactory;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

try {
    $appFactory = new AppFactory();
    $app = $appFactory->create();
    $app->run();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Application failed to start: ' . $e->getMessage()]);
    exit;
}