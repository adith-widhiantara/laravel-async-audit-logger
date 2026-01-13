<?php

namespace Adithwidhiantara\Audit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class AuditPruneCommand extends Command
{
    protected $signature = 'audit:prune {--days= : The number of days to retain audit data}';

    protected $description = 'Prune audit records older than a specified number of days';

    public function handle(): int
    {
        $days = $this->option('days') ?? Config::get('audit.prune_days', 90);

        $this->info("Pruning audits older than {$days} days...");

        $date = now()->subDays((int) $days)->toDateTimeString();

        // Delete Query
        $deleted = DB::table('audits')
            ->where('created_at', '<', $date)
            ->delete();

        $this->info("Deleted {$deleted} old audit records.");

        return self::SUCCESS;
    }
}
