<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditWorkerCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Setup default config
        Config::set('audit.redis.connection', 'default');
        Config::set('audit.redis.queue_key', 'audit_pkg:buffer');
        Config::set('audit.worker.batch_size', 2);
        Config::set('audit.worker.flush_interval', 100); // High interval to rely on batch size
        Config::set('audit.worker.sleep_ms', 1);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob(storage_path('logs/audit_rescue_*.json')));
        parent::tearDown();
    }

    public function test_it_processes_logs_from_redis_and_saves_to_db(): void
    {
        $log1 = json_encode([
            'id' => (string) Str::uuid(),
            'event' => 'created',
            'auditable_type' => 'App\Models\User',
            'auditable_id' => 1,
            'old_values' => json_encode([]),
            'new_values' => json_encode(['name' => 'John']),
            'url' => 'http://example.com',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'created_at' => now()->toDateTimeString(),
        ]);

        $log2 = json_encode([
            'id' => (string) Str::uuid(),
            'event' => 'updated',
            'auditable_type' => 'App\Models\User',
            'auditable_id' => 1,
            'old_values' => json_encode(['name' => 'John']),
            'new_values' => json_encode(['name' => 'Jane']),
            'url' => 'http://example.com',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'created_at' => now()->toDateTimeString(),
        ]);

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturnSelf();

        Redis::shouldReceive('lpop')
            ->with('audit_pkg:buffer')
            ->times(3) // Called in loops
            ->andReturn($log1, $log2, null);

        // Run with max-loops=3 (Process item 1 (buffer 1), Process item 2 (buffer 2 -> flush), Null -> wait (skip))
        // Wait, logic:
        // Loop 1: lpop -> log1 -> buffer[1]. Check flush (size 1 < 2).
        // Loop 2: lpop -> log2 -> buffer[2]. Check flush (size 2 >= 2) -> Flush -> buffer[].
        // Loop 3: lpop -> null -> sleep. Check flush (empty).
        // Loop 4: break (if max-loops=3 and check is at start of loop? No, my check is at start).
        // Code: while.. { loops++; if > max break; }
        // So max-loops=2 might be enough if flush happens at end of loop 2.
        // Yes, flush logic is at end of loop.

        $this->artisan('audit:work', ['--max-loops' => 3])
            ->expectsOutput('Starting Audit Worker (PID: ' . getmypid() . ')...')
            ->expectsOutput('Flushed 2 logs to DB.')
            ->assertExitCode(0);

        $this->assertEquals(2, DB::table('audits')->count());
    }

    public function test_it_rescues_to_file_on_db_failure(): void
    {
        // Provide data that matches schema but we will mock DB to fail OR provide data logic failure?
        // If we mock DB facade, we might interfere with other DB calls.
        // Easiest is to force a DB error by insert data that violates Not Null or similar if possible.
        // But schema is mostly nullable.
        // `event` is string NOT NULL.
        // If we omit `event` in data, insert should fail.

        $logInvalid = json_encode([
            'id' => (string) Str::uuid(),
            // event missing
            'auditable_type' => 'App\Models\User',
            'auditable_id' => 1,
            'created_at' => now()->toDateTimeString(),
        ]);

        Redis::shouldReceive('connection')->andReturnSelf();
        Redis::shouldReceive('lpop')->andReturn($logInvalid, null);

        // Config batch size 1 to force flush immediately
        Config::set('audit.worker.batch_size', 1);

        $this->artisan('audit:work', ['--max-loops' => 2])
            ->expectsOutput('Starting Audit Worker (PID: ' . getmypid() . ')...')
            // It will try to insert, fail, then rescue.
            // Output: Error: ...
            // ->expectsOutput('DB Failed! Rescuing to file... Error: ...')
            // The exact error message depends on DB driver (sqlite).
            // We can assert output contains part of it? expectsOutput must be exact.
            // So we just rely on "Rescued to: ..." which is printed.
            // Wait, "Rescued to: path". Path contains random string and timestamp. Cannot match exact string.
            // So we can't use expectsOutput for the dynamic part.
            // We can check side effects (file existence).
            ->assertExitCode(0);

        $this->assertEquals(0, DB::table('audits')->count());
        $files = glob(storage_path('logs/audit_rescue_*.json'));
        $this->assertCount(1, $files);

        $content = json_decode(file_get_contents($files[0]), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertCount(1, $content['data']);
    }

    public function test_it_skips_invalid_json_from_redis(): void
    {
        $invalidJson = 'not json';

        Redis::shouldReceive('connection')->andReturnSelf();
        Redis::shouldReceive('lpop')->andReturn($invalidJson, null);

        $this->artisan('audit:work', ['--max-loops' => 2])
            ->expectsOutput('Skipping invalid JSON data: not json')
            ->assertExitCode(0);

        $this->assertEquals(0, DB::table('audits')->count());
    }
}
