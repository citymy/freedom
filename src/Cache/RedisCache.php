<?php

declare(strict_types=1);

namespace App\Cache;

use Redis;

class RedisCache
{
    private static ?Redis $instance = null;

    public static function getConnection(): Redis
    {
        if (self::$instance === null) {
            $host = $_ENV['REDIS_HOST'] ?? 'redis';
            $port = (int) ($_ENV['REDIS_PORT'] ?? 6379);

            self::$instance = new Redis();
            self::$instance->connect($host, $port);
        }

        return self::$instance;
    }

    public static function get(string $key): ?string
    {
        $redis = self::getConnection();
        $val = $redis->get($key);
        return $val !== false ? $val : null;
    }

    public static function set(string $key, string $value, int $ttl = 3600): void
    {
        $redis = self::getConnection();
        $redis->setex($key, $ttl, $value);
    }
}
