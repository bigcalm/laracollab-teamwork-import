<?php

namespace LaraCollab\TeamworkImport\Services\Persisters;

use LaraCollab\TeamworkImport\Services\Transformers\TaskListTransformer;

class TaskGroupPersister extends BasePersister
{
    protected string $entityKey = 'tasklists';

    protected string $modelBindingKey = 'task_group';

    protected string $teamworkType = 'tasklist';

    protected string $transformerClass = TaskListTransformer::class;

    public function run(?array $entityFilter = null, ?int $projectId = null): array
    {
        $taskLists = $this->apiClient->getTaskLists($projectId);
        $totalRecords = $taskLists->count();
        $imported = 0;

        foreach ($taskLists as $listData) {
            $attributes = $this->transform($listData);

            $attributes['project_id'] = $this->resolveLocalIdForType(
                $attributes['project_id'] ?? null,
                'project'
            );

            if ($attributes['project_id'] === null) {
                continue;
            }

            $attributes['color'] = $attributes['color'] ?? '#6b7280';
            $attributes['order_column'] = $imported + 1;

            unset($attributes['teamwork_id']);

            $taskGroup = $this->createModel($attributes);
            $this->recordMapping((int) $listData['id'], $taskGroup);
            $imported++;
        }

        return ['imported' => $imported, 'fetched' => $totalRecords];
    }
}
