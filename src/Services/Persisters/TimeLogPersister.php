<?php

namespace LaraCollab\TeamworkImport\Services\Persisters;

use LaraCollab\TeamworkImport\Models\IdMapping;
use LaraCollab\TeamworkImport\Services\Transformers\TimeEntryTransformer;

class TimeLogPersister extends BasePersister
{
    protected string $entityKey = 'time';

    protected string $modelBindingKey = 'time_log';

    protected string $teamworkType = 'time_entry';

    protected string $transformerClass = TimeEntryTransformer::class;

    private ?\Closure $onProjectProgress = null;

    public function setOnProjectProgress(?\Closure $callback): void
    {
        $this->onProjectProgress = $callback;
    }

    public function run(?array $entityFilter = null, ?int $projectId = null): array
    {
        $imported = 0;
        $fetched = 0;
        $skipped = [];

        $projectMappings = IdMapping::where('teamwork_type', 'project')
            ->where('import_run_id', $this->importRun->getKey())
            ->get();

        $total = $projectMappings->count();

        foreach ($projectMappings as $i => $projectMapping) {
            $teamworkProjectId = (int) $projectMapping->teamwork_id;

            $projectName = 'Project #' . $teamworkProjectId;
            $companyName = '';

            $localProject = $projectMapping->local;
            if ($localProject) {
                $projectName = $localProject->name ?? $projectName;
                $companyName = $localProject->clientCompany?->name ?? '';
            }

            if ($this->onProjectProgress) {
                $label = $companyName ? "{$companyName} / {$projectName}" : $projectName;
                call_user_func($this->onProjectProgress, $label, $i + 1, $total);
            }

            $timeEntries = $this->apiClient->getProjectTimeEntries($teamworkProjectId);
            $fetched += $timeEntries->count();

            foreach ($timeEntries as $entryData) {
                $entryId = $entryData['id'] ?? null;
                $attributes = $this->transform($entryData);

                $rawUserId = $attributes['user_id'] ?? null;

                if ($rawUserId !== null) {
                    $attributes['user_id'] = $this->resolveLocalIdForType($rawUserId, 'user')
                        ?? $this->resolveOrCreatePlaceholderUser($rawUserId, $skipped);
                } else {
                    $attributes['user_id'] = null;
                }

                if (($attributes['task_id'] ?? null) === null) {
                    $attributes['task_id'] = $entryData['task']['id'] ?? null;
                }

                $attributes['task_id'] = $this->resolveLocalIdForType(
                    $attributes['task_id'] ?? null,
                    'task'
                );

                if ($attributes['user_id'] === null) {
                    $skipped[] = ['id' => $entryId, 'reason' => 'missing_user'];
                    continue;
                }

                if ($attributes['task_id'] === null) {
                    $skipped[] = ['id' => $entryId, 'reason' => 'missing_task'];
                    continue;
                }

                unset($attributes['teamwork_id']);

                $timeLog = $this->createModel($attributes);
                $this->recordMapping((int) $entryData['id'], $timeLog);
                $imported++;
            }
        }

        return ['imported' => $imported, 'fetched' => $fetched, 'skipped' => $skipped];
    }
}
