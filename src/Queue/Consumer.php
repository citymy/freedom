<?php

declare(strict_types=1);

namespace App\Queue;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use App\Service\RateService;

class Consumer
{
    private AMQPStreamConnection $connection;
    private $channel;
    private RateService $rateService;

    public function __construct(RateService $rateService)
    {
        $this->rateService = $rateService;

        $host = $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq';
        $port = (int) ($_ENV['RABBITMQ_PORT'] ?? 5672);
        $user = $_ENV['RABBITMQ_USER'] ?? 'guest';
        $pass = $_ENV['RABBITMQ_PASS'] ?? 'guest';

        $this->connection = new AMQPStreamConnection($host, $port, $user, $pass);
        $this->channel = $this->connection->channel();

        $this->channel->queue_declare('rates_fetch_queue', false, true, false, false);
    }

    public function consume(): void
    {
        echo " [*] Waiting for messages. To exit press CTRL+C\n";

        $callback = function ($msg) {
            $data = json_decode($msg->body, true);
            $date = $data['date'] ?? null;

            if ($date) {
                echo " [x] Received job for date: $date\n";
                try {
                    $this->rateService->getRate($date, 'EUR', 'RUR');
                    echo " [v] Successfully processed $date\n";
                    $msg->ack();
                } catch (\Exception $e) {
                    echo " [!] Error processing $date: " . $e->getMessage() . "\n";
                    $msg->nack(true);
                }
            } else {
                echo " [!] Invalid message\n";
                $msg->ack();
            }
        };

        $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume('rates_fetch_queue', '', false, false, false, false, $callback);

        while ($this->channel->is_open()) {
            $this->channel->wait();
        }
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
