<?php

namespace Tests\Console\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditPruneCommandTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_it_prunes_audits_based_on_config()
    {
        Config::set('audit.prune_days', 30);

        // Old record
        DB::table('audits')->insert([
            'id' => 1,
            'event' => 'created',
            'created_at' => now()->subDays(31)->toDateTimeString(),
            'url' => 'http://example.com',
            'auditable_type' => 'App\Models\User',
            'auditable_id' => 1,
            'new_values' => '[]',
            'old_values' => '[]',
        ]);

        // New record
        DB::table('audits')->insert([
            'id' => 2,
            'event' => 'created',
            'created_at' => now()->subDays(10)->toDateTimeString(),
            'url' => 'http://example.com',
            'auditable_type' => 'App\Models\User',
            'auditable_id' => 1,
            'new_values' => '[]',
            'old_values' => '[]',
        ]);

        $this->artisan('audit:prune')
            ->assertSuccessful()
            ->expectsOutput('Pruning audits older than 30 days...')
            ->expectsOutput('Deleted 1 old audit records.');

        $this->assertDatabaseMissing('audits', ['id' => 1]);
        $this->assertDatabaseHas('audits', ['id' => 2]);
    }

    public function test_it_prunes_audits_based_on_option()
    {
        Config::set('audit.prune_days', 90);

        // Old record (would be kept by config but pruned by option)
        DB::table('audits')->insert([
            'id' => 1,
            'event' => 'created',
            'created_at' => now()->subDays(60)->toDateTimeString(),
            'url' => 'http://example.com',
            'auditable_type' => 'App\Models\User',
            'auditable_id' => 1,
            'new_values' => '[]',
            'old_values' => '[]',
        ]);

        $this->artisan('audit:prune', ['--days' => 30])
            ->assertSuccessful()
            ->expectsOutput('Pruning audits older than 30 days...')
            ->expectsOutput('Deleted 1 old audit records.');

        $this->assertDatabaseMissing('audits', ['id' => 1]);
    }
}
