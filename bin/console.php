<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Queue\Producer;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$command = $argv[1] ?? null;

if ($command === 'fetch-history') {
    echo "Starting to queue historical fetch jobs for recent 180 days...\n";

    // We wait a bit for rabbitmq to be fully ready if started together via docker-compose
    sleep(2);

    try {
        $producer = new Producer();
    } catch (\Exception $e) {
        die("RabbitMQ connect error: " . $e->getMessage() . "\n");
    }

    $today = new DateTime();
    for ($i = 0; $i < 180; $i++) {
        $dateStr = $today->format('Y-m-d');
        $producer->sendFetchJob($dateStr);
        echo "Queued: $dateStr\n";
        $today->modify('-1 day');
    }

    echo "Done processing 180 days queue.\n";
} else {
    echo "Usage: php bin/console.php fetch-history\n";
}
