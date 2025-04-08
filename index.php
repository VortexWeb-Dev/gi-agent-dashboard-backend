<?php

declare(strict_types=1);

spl_autoload_register(function ($class) {
    require __DIR__ . "/src/{$class}.php";
});

use Helpers\Logger;

require_once __DIR__ . '/helpers/Logger.php';

Logger::logRequest([
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'query' => $_GET,
    'body' => file_get_contents('php://input'),
    'headers' => getallheaders(),
]);

$endpoint = $_GET['endpoint'] ?? null;
$id = $_GET['id'] ?? null;

$controllerClass = $endpoint ? ucfirst($endpoint) . 'Controller' : null;

if ($endpoint && class_exists($controllerClass)) {
    $controller = new $controllerClass();
    $controller->processRequest($_SERVER['REQUEST_METHOD'], $id);
} else {
    header("Content-Type: application/json");
    http_response_code(404);
    echo json_encode(["error" => "Resource '$endpoint' not found"]);
    exit;
}

exit;
