<?php

namespace App\Infrastructure;

use Symfony\Component\Cache\Adapter\RedisAdapter;

final class RedisHealthChecker
{
    public function __construct(private readonly string $dsn) {}

    public function isAvailable(): bool
    {
        try {
            $redis = RedisAdapter::createConnection($this->dsn, ['timeout' => 1]);
            return $redis->ping() === true || $redis->ping() === '+PONG';
        } catch (\Throwable) {
            return false;
        }
    }
}
