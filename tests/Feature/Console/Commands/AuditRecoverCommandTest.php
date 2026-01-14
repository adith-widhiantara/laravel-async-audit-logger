<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AuditRecoverCommandTest extends TestCase
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
        // Clean up any existing rescue files
        foreach (glob(storage_path('logs/audit_rescue_*.json')) as $file) {
            unlink($file);
        }
        parent::tearDown();
    }

    public function test_it_does_nothing_if_no_rescue_files_found()
    {
        $this->artisan('audit:recover')
            ->expectsOutput('No rescue files found.')
            ->assertSuccessful();
    }

    public function test_it_restores_valid_files()
    {
        $data = [
            'error' => 'Simulated error',
            'failed_at' => now()->toDateTimeString(),
            'data' => [
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'event' => 'created',
                    'auditable_type' => 'App\User',
                    'auditable_id' => 1,
                    'created_at' => now()->toDateTimeString(),
                    'new_values' => '[]',
                    'old_values' => '[]',
                ]
            ]
        ];

        file_put_contents(storage_path('logs/audit_rescue_test_1.json'), json_encode($data));

        $this->artisan('audit:recover')
            ->expectsOutput('Found 1 rescue files. Starting recovery...')
            ->expectsOutput('Restored 1 logs from audit_rescue_test_1.json')
            ->expectsOutput('Recovery complete. Total restored: 1')
            ->assertSuccessful();

        $this->assertDatabaseCount('audits', 1);
        $this->assertFalse(file_exists(storage_path('logs/audit_rescue_test_1.json')));
    }

    public function test_it_skips_empty_data_files()
    {
        $data = [
            'error' => 'Simulated error',
            'failed_at' => now()->toDateTimeString(),
            'data' => []
        ];

        file_put_contents(storage_path('logs/audit_rescue_empty.json'), json_encode($data));

        $this->artisan('audit:recover')
            ->expectsOutput('Found 1 rescue files. Starting recovery...')
            ->expectsOutput('Skipping empty/invalid file: audit_rescue_empty.json')
            ->expectsOutput('Recovery complete. Total restored: 0')
            ->assertSuccessful();

        $this->assertDatabaseCount('audits', 0);
        // File should still exist as code doesn't unlink if skipped (based on logic reading) or maybe it remains? 
        // Logic: if (empty($json['data'])) { warn... continue; } -> unlink is AFTER continue. So file remains.
        $this->assertTrue(file_exists(storage_path('logs/audit_rescue_empty.json')));
    }

    public function test_it_handles_restoration_errors()
    {
        $data = [
            'error' => 'Simulated error',
            'failed_at' => now()->toDateTimeString(),
            'data' => [
                ['invalid_column' => 'value'] // This will fail insert
            ]
        ];

        file_put_contents(storage_path('logs/audit_rescue_fail.json'), json_encode($data));

        $this->artisan('audit:recover')
            ->expectsOutput('Found 1 rescue files. Starting recovery...')
            ->expectsOutputToContain('Failed to restore audit_rescue_fail.json') // Partial match
            ->expectsOutput('Recovery complete. Total restored: 0')
            ->assertSuccessful();

        $this->assertDatabaseCount('audits', 0);
        // File should remain on error
        $this->assertTrue(file_exists(storage_path('logs/audit_rescue_fail.json')));
    }
}
