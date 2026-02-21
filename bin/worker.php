<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Queue\Consumer;
use App\Infrastructure\CbrClient;
use App\Repository\RateRepository;
use App\Service\RateService;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

echo "Worker is starting...\n";
sleep(5); // Wait for RabbitMQ & DB to boot

try {
    $cbrClient = new CbrClient();
    $rateRepository = new RateRepository();
    $rateService = new RateService($rateRepository, $cbrClient);

    $consumer = new Consumer($rateService);
    $consumer->consume();
} catch (\Exception $e) {
    echo "Fatal error in worker: " . $e->getMessage() . "\n";
    exit(1);
}
