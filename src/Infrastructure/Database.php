<?php

declare(strict_types=1);

namespace App\Infrastructure;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $host = $_ENV['DB_HOST'] ?? 'postgres';
            $port = $_ENV['DB_PORT'] ?? '5432';
            $db = $_ENV['DB_NAME'] ?? 'finance';
            $user = $_ENV['DB_USER'] ?? 'finance_user';
            $pass = $_ENV['DB_PASS'] ?? 'finance_pass';

            $dsn = "pgsql:host=$host;port=$port;dbname=$db";

            $maxRetries = 10;
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    self::$instance = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);
                    break; // connected successfully
                } catch (PDOException $e) {
                    if ($attempt === $maxRetries) {
                        throw new PDOException("DB Connection failed after $maxRetries attempts: " . $e->getMessage(), (int) $e->getCode());
                    }
                    echo "Waiting for PostgreSQL to start (attempt $attempt/$maxRetries)...\n";
                    sleep(2);
                }
            }
        }

        return self::$instance;
    }
}
