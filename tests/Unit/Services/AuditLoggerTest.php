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
                return str_contains($message, 'AuditLogger Redis Error:');
            })
            ->once();

        AuditLogger::push(['test' => 'data']);
    }

    public function test_audit_logger_inserts_to_db_when_driver_is_sync()
    {
        Config::set('audit.driver', 'sync');

        // Mocking DB facade for insert is cleaner if we just check side effects on the real test DB
        // The AuditLogger::push uses DB::table('audits')->insert($data)

        $data = [
            'id' => \Illuminate\Support\Str::uuid(),
            'event' => 'created',
            'auditable_type' => 'App\User',
            'auditable_id' => 1,
            'created_at' => now()->toDateTimeString(),
            'new_values' => '[]',
            'old_values' => '[]'
        ];

        Log::shouldReceive('error')->never();

        AuditLogger::push($data);

        $this->assertDatabaseHas('audits', ['id' => $data['id']]);
    }

    public function test_audit_logger_logs_error_when_sync_fails()
    {
        Config::set('audit.driver', 'sync');

        // Force an error by passing invalid data that causes DB exception (e.g. missing required field or bad format)
        // OR mock DB. Since we are in Integration/Unit test with Orchestra, we can try mocking DB facade 
        // OR easier: use invalid data.

        $data = ['invalid_column' => 'value'];

        Log::shouldReceive('error')
            ->withArgs(function ($message) {
                return str_contains($message, 'AuditLogger Sync Error:');
            })
            ->once();

        AuditLogger::push($data);
    }
}
