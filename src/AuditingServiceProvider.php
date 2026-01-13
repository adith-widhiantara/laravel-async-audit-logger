<?php

declare(strict_types=1);

namespace Adithwidhiantara\Audit;

use Adithwidhiantara\Audit\Console\Commands\AuditWorkerCommand;
use Illuminate\Support\ServiceProvider;

class AuditingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/audit.php', 'audit'
        );
    }

    public function boot(): void
    {
        if($this->app->runningInConsole()){
            $this->commands([
                AuditWorkerCommand::class,
            ]);

            $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

            $this->publishes([
                __DIR__ . '/../config/audit.php' => config_path('audit.php'),
            ], 'audit-config');
        }
    }
}
