<?php

namespace LaraCollab\TeamworkImport;

use Illuminate\Support\ServiceProvider;
use LaraCollab\TeamworkImport\Console\ImportFilesCommand;
use LaraCollab\TeamworkImport\Console\ImportTeamworkCommand;

class TeamworkImportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/teamwork.php',
            'teamwork'
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

        $this->publishes([
            __DIR__ . '/../config/teamwork.php' => config_path('teamwork.php'),
        ], 'teamwork-config');

        $this->publishes([
            __DIR__ . '/Database/Migrations' => database_path('migrations'),
        ], 'teamwork-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportTeamworkCommand::class,
                ImportFilesCommand::class,
            ]);
        }
    }
}
