<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Controller\RateController;
use App\Infrastructure\CbrClient;
use App\Repository\RateRepository;
use App\Service\RateService;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Allow Cross-Origin Requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($uri === '/api/v1/rates') {
    $cbrClient = new CbrClient();
    $rateRepository = new RateRepository();
    $rateService = new RateService($rateRepository, $cbrClient);
    $controller = new RateController($rateService);

    $controller->handleRequest($_GET);
} else {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not Found']);
}
