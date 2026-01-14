<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class AuditWorkerCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clean up any existing rescue files
        foreach (glob(storage_path('logs/audit_rescue_*.json')) as $file) {
            unlink($file);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob(storage_path('logs/audit_rescue_*.json')) as $file) {
            unlink($file);
        }
        parent::tearDown();
    }

    public function test_worker_processes_jobs_from_redis()
    {
        Config::set('audit.driver', 'redis');
        Config::set('audit.redis.connection', 'default');
        Config::set('audit.redis.queue_key', 'audit_pkg:buffer');
        Config::set('audit.worker.batch_size', 2);

        $log1 = [
            'id' => \Illuminate\Support\Str::uuid(),
            'event' => 'created',
            'auditable_type' => 'App\User',
            'auditable_id' => 1,
            'created_at' => now()->toDateTimeString(),
            'new_values' => '[]',
            'old_values' => '[]',
        ];
        $log2 = [
            'id' => \Illuminate\Support\Str::uuid(),
            'event' => 'updated',
            'auditable_type' => 'App\User',
            'auditable_id' => 1,
            'created_at' => now()->toDateTimeString(),
            'new_values' => '[]',
            'old_values' => '[]',
        ];

        Redis::shouldReceive('connection')->with('default')->andReturnSelf();

        // Return 2 logs, then null to simulate empty queue
        Redis::shouldReceive('lpop')
            ->with('audit_pkg:buffer')
            ->times(3)
            ->andReturn(
                json_encode($log1),
                json_encode($log2),
                null
            );

        $this->artisan('audit:work', ['--max-loops' => 3])
            ->expectsOutput('Starting Audit Worker (PID: '.getmypid().')...')
            ->expectsOutput('Flushed 2 logs to DB.')
            ->assertSuccessful();

        $this->assertDatabaseCount('audits', 2);
    }

    public function test_worker_rescues_to_file_on_db_failure()
    {
        Config::set('audit.driver', 'redis');
        Config::set('audit.redis.connection', 'default');
        Config::set('audit.redis.queue_key', 'audit_pkg:buffer');
        Config::set('audit.worker.batch_size', 1);

        // Pre-insert a record to cause duplicate key error later
        $dupId = \Illuminate\Support\Str::uuid()->toString();
        DB::table('audits')->insert([
            'id' => $dupId,
            'event' => 'created',
            'auditable_type' => 'App\User',
            'auditable_id' => 1,
            'created_at' => now()->toDateTimeString(),
            'new_values' => '[]',
            'old_values' => '[]',
        ]);

        $badLog = [
            'id' => $dupId, // Duplicate ID
            'event' => 'updated',
            'auditable_type' => 'App\User',
            'auditable_id' => 1,
            'created_at' => now()->toDateTimeString(),
            'new_values' => '[]',
            'old_values' => '[]',
        ];

        Redis::shouldReceive('connection')->with('default')->andReturnSelf();
        Redis::shouldReceive('lpop')
            ->with('audit_pkg:buffer')
            ->andReturn(json_encode($badLog));

        $this->artisan('audit:work', ['--max-loops' => 1])
            ->expectsOutputToContain('DB Failed! Rescuing to file...')
            ->assertSuccessful();

        // Count should still be 1 (original record), insert failed
        $this->assertDatabaseCount('audits', 1);
        $files = glob(storage_path('logs/audit_rescue_*.json'));
        $this->assertCount(1, $files);
    }

    public function test_worker_skips_invalid_json()
    {
        Config::set('audit.driver', 'redis');
        Redis::shouldReceive('connection')->andReturnSelf();
        Redis::shouldReceive('lpop')->andReturn('INVALID_JSON');

        $this->artisan('audit:work', ['--max-loops' => 1])
            ->expectsOutputToContain('Skipping invalid JSON data')
            ->assertSuccessful();
    }
}
