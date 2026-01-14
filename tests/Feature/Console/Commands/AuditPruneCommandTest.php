<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditPruneCommandTest extends TestCase
{
    public function test_it_prunes_audits_older_than_config_days()
    {
        Config::set('audit.prune_days', 30);

        // Old record (should be deleted)
        DB::table('audits')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'event' => 'created',
            'auditable_type' => 'App\User',
            'auditable_id' => 1,
            'created_at' => now()->subDays(31)->toDateTimeString(),
            'new_values' => '[]',
            'old_values' => '[]',
        ]);

        // New record (should be kept)
        DB::table('audits')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'event' => 'created',
            'auditable_type' => 'App\User',
            'auditable_id' => 1,
            'created_at' => now()->subDays(29)->toDateTimeString(),
            'new_values' => '[]',
            'old_values' => '[]',
        ]);

        $this->artisan('audit:prune')
            ->expectsOutput('Pruning audits older than 30 days...')
            ->expectsOutput('Deleted 1 old audit records.')
            ->assertSuccessful();

        $this->assertDatabaseCount('audits', 1);
    }

    public function test_it_prunes_audits_older_than_specified_days_option()
    {
        // Old record (should be deleted)
        DB::table('audits')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'event' => 'created',
            'auditable_type' => 'App\User',
            'auditable_id' => 1,
            'created_at' => now()->subDays(11)->toDateTimeString(),
            'new_values' => '[]',
            'old_values' => '[]',
        ]);

        // New record (should be kept)
        DB::table('audits')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'event' => 'created',
            'auditable_type' => 'App\User',
            'auditable_id' => 1,
            'created_at' => now()->subDays(9)->toDateTimeString(),
            'new_values' => '[]',
            'old_values' => '[]',
        ]);

        $this->artisan('audit:prune', ['--days' => 10])
            ->expectsOutput('Pruning audits older than 10 days...')
            ->expectsOutput('Deleted 1 old audit records.')
            ->assertSuccessful();

        $this->assertDatabaseCount('audits', 1);
    }
}
