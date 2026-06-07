<?php

namespace LaraCollab\TeamworkImport\Console;

use Illuminate\Console\Command;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\ImportService;

class ImportFilesCommand extends Command
{
    protected $signature = 'teamwork:import-files
        {--project=* : One or more Teamwork project IDs to import files for (all projects if omitted)}';

    protected $description = 'Import project files and link them to tasks';

    public function handle(): int
    {
        $projectIds = $this->option('project');
        $projectIds = ! empty($projectIds) ? array_map('intval', $projectIds) : null;

        $lastRun = ImportRun::whereIn('status', ['completed', 'partial'])->latest()->first();

        if (! $lastRun) {
            $this->error('No completed or partial import run found. Run teamwork:import first.');
            return self::FAILURE;
        }

        $importRun = $lastRun;

        $this->line(' <fg=cyan>Teamwork File Import</>');
        $this->line(' <fg=gray>' . str_repeat('─', 60) . '</>');

        $projectCount = $projectIds !== null ? count($projectIds) : 'all';
        $this->line(" <comment>Importing files for {$projectCount} project(s)...</>");

        $apiClient = new ApiClient;
        $idMappingService = new IdMappingService;

        $persister = new \LaraCollab\TeamworkImport\Services\Persisters\ProjectFilePersister(
            $importRun,
            $apiClient,
            $idMappingService,
            filterProjectIds: $projectIds,
            onProgress: function (string $projectName, int $current, int $total) {
                $this->line(" <comment>[{$current}/{$total}]</comment> <fg=cyan>{$projectName}</>");
            },
        );

        try {
            $result = $persister->run();

            $imported = $result['imported'] ?? 0;
            $skipped = $result['skipped'] ?? [];

            $this->line(" <comment>Files</comment> <fg=green>✔</> <fg=gray>{$imported} imported</>");

            if (! empty($skipped)) {
                $this->newLine();
                $this->line(' <fg=yellow>Skipped records:</>');
                $byReason = [];
                foreach ($skipped as $r) {
                    $reason = $r['reason'] ?? 'unknown';
                    $byReason[$reason][] = $r['id'];
                }
                foreach ($byReason as $reason => $ids) {
                    $count = count($ids);
                    if ($count <= 20) {
                        $this->line("   - {$reason}: <fg=gray>" . implode(', ', $ids) . '</>');
                    } else {
                        $this->line("   - {$reason}: <fg=gray>{$count} records</>");
                    }
                }
            }

            $this->newLine();
            $this->info(' File import completed successfully.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error(' File import failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
