<?php

declare(strict_types=1);

namespace App\Queue;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Producer
{
    private AMQPStreamConnection $connection;
    private $channel;

    public function __construct()
    {
        $host = $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq';
        $port = (int) ($_ENV['RABBITMQ_PORT'] ?? 5672);
        $user = $_ENV['RABBITMQ_USER'] ?? 'guest';
        $pass = $_ENV['RABBITMQ_PASS'] ?? 'guest';

        $this->connection = new AMQPStreamConnection($host, $port, $user, $pass);
        $this->channel = $this->connection->channel();

        $this->channel->queue_declare('rates_fetch_queue', false, true, false, false);
    }

    public function sendFetchJob(string $date): void
    {
        $data = json_encode(['date' => $date]);
        $msg = new AMQPMessage($data, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);

        $this->channel->basic_publish($msg, '', 'rates_fetch_queue');
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
