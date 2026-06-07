<?php

namespace LaraCollab\TeamworkImport\Console;

use Illuminate\Console\Command;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\ImportService;

class ImportTeamworkCommand extends Command
{
    protected $signature = 'teamwork:import
        {--entities= : Comma-separated subset of entities to import}
        {--role= : Override the default role for imported users}
        {--project= : Only import a single Project ID from Teamwork}
        {--queue : Dispatch to queue for async processing}';

    protected $description = 'Import data from Teamwork.com API v3 into LaraCollab';

    private function entityLabel(string $key): string
    {
        return match ($key) {
            'users' => 'Users',
            'companies' => 'Companies',
            'tags' => 'Labels',
            'projects' => 'Projects',
            'tasklists' => 'Task Groups',
            'tasks' => 'Tasks',
            'time' => 'Time Logs',
            'comments' => 'Comments',
            'files' => 'Attachments',
            default => $key,
        };
    }

    public function handle(): int
    {
        $entities = $this->option('entities')
            ? explode(',', $this->option('entities'))
            : null;

        $role = $this->option('role');
        $projectId = $this->option('project');

        $lastRun = ImportRun::whereIn('status', ['running', 'partial'])->latest()->first();

        if ($lastRun && ! $this->option('no-interaction') && $this->confirm(
            "Previous import (#{$lastRun->getKey()}) was left in '{$lastRun->status}' state. Resume?"
        )) {
            $importRun = $lastRun;
            $importRun->update(['status' => 'running', 'started_at' => now()]);
            $this->line(" <fg=cyan>Resuming import #{$importRun->getKey()}</>");
        } else {
            if ($lastRun) {
                $lastRun->update(['status' => 'failed']);
            }
            $importRun = ImportRun::create([
                'status' => 'running',
                'started_at' => now(),
            ]);
        }

        $completedEntities = array_keys($importRun->entities_imported ?? []);

        $this->newLine();
        $this->line(' <fg=cyan>Teamwork Import</>');
        $this->line(' <fg=gray>' . str_repeat('─', 60) . '</>');

        $apiClient = new ApiClient;
        $idMappingService = new IdMappingService;
        $importService = new ImportService($apiClient, $idMappingService);

        try {
            $importService->run(
                importRun: $importRun,
                entityFilter: $entities,
                role: $role,
                projectId: $projectId,
                skipEntities: $completedEntities,
                onProgress: function (string $phase, ?string $key, ?string $label, int $n1, int $n2, mixed ...$extra) {
                    if ($phase === 'before') {
                        $this->output->write(" <comment>{$label}</comment> ");
                    }

                    if ($phase === 'page') {
                        [$totalSoFar, $totalPages] = $extra;
                        $pageInfo = $totalPages ? "page {$n1}/{$totalPages}" : "page {$n1}";
                        $this->output->write("\r <comment>{$label}</comment> <fg=gray>{$pageInfo} ({$totalSoFar} records)...</>");
                    }

                    if ($phase === 'project') {
                        $this->output->write("\r <comment>Time Logs</comment> <fg=cyan>{$label}</> <fg=gray>[{$n1}/{$n2}]</>  ");
                    }

                    if ($phase === 'after') {
                        [$fetched, $error] = $extra;
                        $imported = $n1;
                        $line = '';

                        if ($fetched > 0) {
                            $detail = "{$imported}/{$fetched}";

                            if ($error) {
                                $line = " <comment>{$label}</comment> <fg=yellow>⚠</> <fg=red>{$detail}</> <fg=yellow>({$error})</>";
                            } elseif ($imported < $fetched) {
                                $line = " <comment>{$label}</comment> <fg=yellow>⚠</> <fg=gray>{$detail} imported (some skipped)</>";
                            } else {
                                $line = " <comment>{$label}</comment> <fg=green>✔</> <fg=gray>{$detail} imported</>";
                            }
                        } else {
                            if ($error) {
                                $line = " <comment>{$label}</comment> <fg=yellow>⚠</> <fg=red>0</> <fg=yellow>({$error})</>";
                            } else {
                                $line = " <comment>{$label}</comment> <fg=yellow>⚠</> <fg=gray>0 fetched from API</>";
                            }
                        }

                        $this->output->write("\r" . $line . str_repeat(' ', 40) . "\n");
                    }

                    if ($phase === 'done') {
                        [$entitiesImported, $errors, $allSkipped, $allRationalised] = $extra;

                        $totalImported = is_array($entitiesImported) ? array_sum($entitiesImported) : 0;
                        $entityCount = is_array($entitiesImported) ? count($entitiesImported) : 0;

                        $this->line(' <fg=gray>' . str_repeat('─', 60) . '</>');
                        $this->line(" <info>Total: {$totalImported} items across {$entityCount} entities</info>");

                        $hasRationalised = ! empty($allRationalised) && array_sum($allRationalised) > 0;
                        if ($hasRationalised) {
                            foreach ($allRationalised as $entityKey => $count) {
                                if ($count <= 0) continue;
                                $label = $this->entityLabel($entityKey);
                                $this->line("   <fg=cyan>ℹ {$label}:</> {$count} duplicates mapped to existing records");
                            }
                        }

                        $hasSkips = false;
                        if (! empty($allSkipped)) {
                            foreach ($allSkipped as $entityKey => $records) {
                                if (empty($records)) continue;
                                $hasSkips = true;
                                break;
                            }
                        }

                        if ($hasSkips) {
                            $this->newLine();
                            $this->line(' <fg=yellow>Skipped records:</>');
                            foreach ($allSkipped as $entityKey => $records) {
                                if (empty($records)) continue;
                                $label = $this->entityLabel($entityKey);
                                $byReason = [];
                                foreach ($records as $r) {
                                    $reason = $r['reason'] ?? 'unknown';
                                    $byReason[$reason][] = $r['id'];
                                }
                                $totalSkipped = count($records);
                                $this->line("   <fg=yellow>{$label}:</> {$totalSkipped} records");
                                foreach ($byReason as $reason => $ids) {
                                    $count = count($ids);
                                    if ($count <= 20) {
                                        $this->line("      - {$reason}: <fg=gray>" . implode(', ', $ids) . "</>");
                                    } else {
                                        $this->line("      - {$reason}: <fg=gray>{$count} records</>");
                                    }
                                }
                            }
                        }

                        if (! empty($errors)) {
                            $this->newLine();
                            $this->line(' <fg=yellow>Errors:</>');
                            foreach ($errors as $err) {
                                $this->line('   <fg=red>✗</> ' . $err['entity'] . ': ' . $err['error']);
                            }
                        }
                    }
                },
            );

            $importRun->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $this->newLine();
            $this->info(' Import completed successfully.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $importRun->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);

            $this->newLine();
            $this->error(' Import failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
