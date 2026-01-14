<?php

namespace Adithwidhiantara\Audit\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class AuditLogger
{
    /**
     * Push raw data to Redis List.
     */
    public static function push(array $data): void
    {
        $driver = Config::get('audit.driver', 'redis');

        // MODE 1: SYNC
        if ($driver === 'sync') {
            try {
                DB::table('audits')->insert($data);
            } catch (Throwable $e) {
                Log::error("AuditLogger Sync Error: " . $e->getMessage());
            }
            return;
        }

        // MODE 2: REDIS
        if ($driver === 'redis') {
            try {
                $connection = Config::get('audit.redis.connection', 'default');
                $key = Config::get('audit.redis.queue_key', 'audit_pkg:buffer');

                $payload = json_encode($data, JSON_THROW_ON_ERROR);

                Redis::connection($connection)->rpush($key, $payload);
            } catch (Throwable $e) {
                Log::error("AuditLogger Redis Error: " . $e->getMessage());
            }
        }
    }
}
