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

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = array_values(array_filter(explode("/", $uri)));

$route = null;
$id = $_GET['id'] ?? null;

$rankingIndex = array_search('ranking', $parts);
$performanceIndex = array_search('performance', $parts);

if ($rankingIndex !== false) {
    $route = 'ranking';

    $controller = new RankingController();
    $controller->processRequest($_SERVER['REQUEST_METHOD'], $id);
} elseif ($performanceIndex !== false) {
    $route = 'performance';

    $controller = new PerformanceController();
    $controller->processRequest($_SERVER['REQUEST_METHOD'], $id);
} else {
    header("Content-Type: application/json");
    http_response_code(404);
    echo json_encode(["error" => "Resource not found"]);
    exit;
}

exit;
