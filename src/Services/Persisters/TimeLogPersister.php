<?php

namespace LaraCollab\TeamworkImport\Services\Persisters;

use LaraCollab\TeamworkImport\Services\Transformers\TimeEntryTransformer;

class TimeLogPersister extends BasePersister
{
    protected string $entityKey = 'time';

    protected string $modelBindingKey = 'time_log';

    protected string $teamworkType = 'time_entry';

    protected string $transformerClass = TimeEntryTransformer::class;

    public function run(?array $entityFilter = null, ?int $projectId = null): array
    {
        $timeEntries = $this->apiClient->getTimeEntries();
        $totalRecords = $timeEntries->count();
        $imported = 0;
        $skipped = [];

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

        return ['imported' => $imported, 'fetched' => $totalRecords, 'skipped' => $skipped];
    }
}
