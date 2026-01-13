<?php

namespace Adithwidhiantara\Audit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class AuditRecoverCommand extends Command
{
    protected $signature = 'audit:recover';
    protected $description = 'Recover failed audit logs from disk storage';

    public function handle(): int
    {
        $pattern = storage_path('logs/audit_rescue_*.json');
        $files = glob($pattern);

        if (empty($files)) {
            $this->info("No rescue files found.");
            return self::SUCCESS;
        }

        $this->info("Found " . count($files) . " rescue files. Starting recovery...");

        $totalRestored = 0;

        foreach ($files as $file) {
            try {
                $content = file_get_contents($file);
                $json = json_decode($content, true);

                if (empty($json['data'])) {
                    $this->warn("Skipping empty/invalid file: " . basename($file));
                    continue;
                }

                $logs = $json['data'];
                $count = count($logs);

                DB::table('audits')->insert($logs);

                unlink($file);

                $this->info("Restored {$count} logs from " . basename($file));
                $totalRestored += $count;

            } catch (Throwable $e) {
                $this->error("Failed to restore " . basename($file) . ": " . $e->getMessage());
            }
        }

        $this->info("Recovery complete. Total restored: {$totalRestored}");
        return self::SUCCESS;
    }
}
