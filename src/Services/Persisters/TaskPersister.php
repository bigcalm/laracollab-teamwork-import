<?php

namespace LaraCollab\TeamworkImport\Services\Persisters;

use LaraCollab\TeamworkImport\Services\Transformers\TaskTransformer;

class TaskPersister extends BasePersister
{
    protected string $entityKey = 'tasks';

    protected string $modelBindingKey = 'task';

    protected string $teamworkType = 'task';

    protected string $transformerClass = TaskTransformer::class;

    public function run(?array $entityFilter = null, ?int $projectId = null): array
    {
        $imported = 0;
        $skipped = [];
        $modelClass = $this->getModelClass();
        $taskNumber = $modelClass::max('number') ?? 0;

        $projectMappings = \LaraCollab\TeamworkImport\Models\IdMapping::where('teamwork_type', 'project')
            ->where('import_run_id', $this->importRun->getKey())
            ->get();

        $totalRecords = 0;

        foreach ($projectMappings as $projectMapping) {
            $teamworkProjectId = $projectMapping->teamwork_id;
            $localProjectId = $projectMapping->local_id;

            $tasks = $this->apiClient->getTasks($teamworkProjectId);
            $totalRecords += $tasks->count();

            foreach ($tasks as $taskData) {
                $taskId = $taskData['id'] ?? null;
                $attributes = $this->transform($taskData);

                $attributes['project_id'] = $localProjectId;

                $rawGroupId = $attributes['group_id'] ?? null;
                $attributes['group_id'] = $this->resolveLocalIdForType(
                    $rawGroupId,
                    'tasklist'
                );

                if ($attributes['group_id'] === null) {
                    $attributes['group_id'] = $this->ensureDefaultTaskGroup($localProjectId, $rawGroupId);
                }

                if ($attributes['group_id'] === null) {
                    $skipped[] = ['id' => $taskId, 'reason' => 'missing_tasklist'];
                    continue;
                }

            $attributes['assigned_to_user_id'] = $this->resolveLocalIdForType(
                $attributes['assigned_to_user_id'] ?? null,
                'user'
            );

            if (isset($attributes['created_by_user_id'])) {
                $raw = (int) $attributes['created_by_user_id'];
                $attributes['created_by_user_id'] = $this->resolveLocalIdForType($raw, 'user')
                    ?? $this->resolveOrCreatePlaceholderUser($raw, $skipped);
            }

            if (isset($attributes['priority_id'])) {
                $priorityModelClass = config('teamwork.models.task_priority');
                $label = $attributes['priority_id'];
                $label = $this->normalisePriorityLabel($label);
                $priority = $priorityModelClass::where('label', $label)->orWhere('id', $label)->first();
                $attributes['priority_id'] = $priority?->getKey();
            }

                $taskNumber++;
                $attributes['number'] = $taskNumber;
                $attributes['order_column'] = $taskNumber;

                $tagIds = $taskData['tagIds'] ?? [];

                unset($attributes['teamwork_id']);

                $task = $this->createModel($attributes);

                if (! empty($tagIds)) {
                    $localLabelIds = [];

                    foreach ($tagIds as $tagId) {
                        $localLabel = $this->idMappingService->find((int) $tagId, 'tag');

                        if ($localLabel) {
                            $localLabelIds[] = $localLabel->getKey();
                        }
                    }

                    if (! empty($localLabelIds)) {
                        $task->labels()->syncWithoutDetaching($localLabelIds);
                    }
                }

                $this->syncSubscribers($taskData, $task);

                $this->recordMapping((int) $taskData['id'], $task);
                $imported++;
            }
        }

        return ['imported' => $imported, 'fetched' => $totalRecords, 'skipped' => $skipped];
    }

    private function ensureDefaultTaskGroup(int $projectId, ?int $teamworkTasklistId): ?int
    {
        $taskGroupClass = config('teamwork.models.task_group');

        $group = $taskGroupClass::where('project_id', $projectId)
            ->where('name', 'Imported')
            ->first();

        if ($group) {
            return $group->getKey();
        }

        $maxOrder = $taskGroupClass::where('project_id', $projectId)->max('order_column') ?? 0;

        $group = $taskGroupClass::withoutEvents(function () use ($taskGroupClass, $projectId, $maxOrder) {
            return $taskGroupClass::create([
                'project_id' => $projectId,
                'name' => 'Imported',
                'color' => '#6b7280',
                'order_column' => $maxOrder + 1,
            ]);
        });

        if ($teamworkTasklistId !== null) {
            $this->recordMapping($teamworkTasklistId, $group);
        }

        return $group->getKey();
    }

    private function normalisePriorityLabel(string $label): string
    {
        $map = [
            'urgent' => 'Very high',
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
            'very low' => 'Very low',
            'none' => 'Low',
        ];

        return $map[strtolower($label)] ?? ucfirst(strtolower($label));
    }

    private function syncSubscribers(array $taskData, $task): void
    {
        $followers = $taskData['commentFollowers'] ?? [];

        if (empty($followers)) {
            return;
        }

        $localUserIds = [];

        foreach ($followers as $follower) {
            if (($follower['type'] ?? null) !== 'users') {
                continue;
            }

            $localUser = $this->idMappingService->find((int) $follower['id'], 'user');

            if ($localUser) {
                $localUserIds[] = $localUser->getKey();
            }
        }

        if (! empty($localUserIds)) {
            $task->subscribedUsers()->syncWithoutDetaching($localUserIds);
        }
    }
}
