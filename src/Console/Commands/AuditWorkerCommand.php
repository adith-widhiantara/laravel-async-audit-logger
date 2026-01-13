<?php

declare(strict_types=1);

namespace Adithwidhiantara\Audit\Console\Commands;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class AuditWorkerCommand extends \Illuminate\Console\Command
{
    protected $signature = 'audit:work';

    protected $description = 'Process audit logs from Redis buffer to Database';

    private array $buffer = [];

    private int $lastFlushTime;

    private bool $shouldExit = false;

    public function handle(): int
    {
        $this->info('Starting Audit Worker (PID: '.getmypid().')...');
        $this->lastFlushTime = time();

        $this->trap([SIGTERM, SIGINT, SIGQUIT], function ($signal) {
            $this->info("Signal $signal received. Flushing buffer before exit...");
            $this->flushBuffer();
            $this->shouldExit = true;
        });

        $redisConn = Config::get('audit.redis.connection', 'default');
        $queueKey = Config::get('audit.redis.queue_key', 'audit_pkg:buffer');
        $batchSize = (int) Config::get('audit.worker.batch_size', 100);
        $flushInt = (int) Config::get('audit.worker.flush_interval', 5);
        $sleepMs = (int) Config::get('audit.worker.sleep_ms', 500);

        while (! $this->shouldExit) {
            try {
                $rawLog = Redis::connection($redisConn)->lpop($queueKey);

                if ($rawLog) {
                    $decoded = json_decode($rawLog, true);

                    if (is_array($decoded)) {
                        $this->buffer[] = $decoded;
                    } else {
                        $this->warn('Skipping invalid JSON data: '.Str::limit($rawLog, 50));
                    }
                } else {
                    usleep($sleepMs * 1000);
                }

                // Cek Trigger Flush
                $isBufferFull = count($this->buffer) >= $batchSize;
                $isTimeUp = (time() - $this->lastFlushTime) >= $flushInt;

                if (! empty($this->buffer) && ($isBufferFull || $isTimeUp)) {
                    $this->flushBuffer();
                }

            } catch (Throwable $e) {
                $this->error('Worker Error: '.$e->getMessage());
                sleep(1);
            }
        }

        return self::SUCCESS;
    }

    private function flushBuffer(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        try {
            // PLAN A: Insert ke Database
            DB::table('audits')->insert($this->buffer);

            $this->info('Flushed '.count($this->buffer).' logs to DB.');

        } catch (Throwable $e) {
            // PLAN B: Insert Gagal? Simpan ke File (Rescue)
            $this->error('DB Failed! Rescuing to file... Error: '.$e->getMessage());
            $this->rescueToFile($e->getMessage());
        }

        // Reset Buffer
        $this->buffer = [];
        $this->lastFlushTime = time();
    }

    /**
     * @throws RandomException
     */
    private function rescueToFile(string $errorMessage): void
    {
        $timestamp = date('Y-m-d_H-i-s');
        $random = bin2hex(random_bytes(4));
        $filename = "audit_rescue_{$timestamp}_{$random}.json";
        $path = storage_path("logs/{$filename}");

        $payload = [
            'error' => $errorMessage,
            'failed_at' => now()->toDateTimeString(),
            'data' => $this->buffer,
        ];

        // Tulis ke disk
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

        $this->warn("Rescued to: $path");
    }
}
