<?php

namespace Adithwidhiantara\Audit\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogger
{
    /**
     * Push raw data to Redis List.
     */
    public static function push(array $data): void
    {
        // Cek apakah driver di-enable
        if (Config::get('audit.driver') !== 'redis') {
            return;
        }

        try {
            // Ambil konfigurasi
            $connection = Config::get('audit.redis.connection', 'default');
            $key        = Config::get('audit.redis.queue_key', 'audit_pkg:buffer');

            // Encode ke JSON (flags untuk memastikan float/array aman)
            $payload = json_encode($data, JSON_THROW_ON_ERROR);

            // RAW REDIS COMMAND: RPUSH (Insert di ekor antrian)
            // Ini operasi atomik yang sangat cepat (< 1ms)
            Redis::connection($connection)->rpush($key, $payload);

        } catch (Throwable $e) {
            // Fail-safe: Jangan biarkan logging error mematikan aplikasi utama user
            // Kita catat saja di log file Laravel
            Log::error("AuditLogger Error: Failed to push to Redis. " . $e->getMessage());
        }
    }
}
