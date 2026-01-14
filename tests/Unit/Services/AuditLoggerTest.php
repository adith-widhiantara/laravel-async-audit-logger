<?php

namespace Tests\Unit\Services;

use Adithwidhiantara\Audit\Services\AuditLogger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    public function test_audit_logger_skips_when_driver_is_not_redis()
    {
        Config::set('audit.driver', 'file');

        Redis::shouldReceive('connection')->never();

        AuditLogger::push(['test' => 'data']);
    }

    public function test_audit_logger_pushes_to_redis_when_driver_is_redis()
    {
        Config::set('audit.driver', 'redis');
        Config::set('audit.redis.connection', 'default');
        Config::set('audit.redis.queue_key', 'audit_pkg:buffer');

        Redis::shouldReceive('connection')
            ->with('default')
            ->once()
            ->andReturnSelf();

        Redis::shouldReceive('rpush')
            ->with('audit_pkg:buffer', json_encode(['test' => 'data'], JSON_THROW_ON_ERROR))
            ->once();

        AuditLogger::push(['test' => 'data']);
    }

    public function test_audit_logger_logs_error_when_redis_fails()
    {
        Config::set('audit.driver', 'redis');

        Redis::shouldReceive('connection')
            ->andThrow(new \Exception('Redis connection failed'));

        Log::shouldReceive('error')
            ->withArgs(function ($message) {
                return str_contains($message, 'AuditLogger Error: Failed to push to Redis');
            })
            ->once();

        AuditLogger::push(['test' => 'data']);
    }
}
