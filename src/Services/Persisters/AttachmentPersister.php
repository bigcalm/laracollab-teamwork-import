<?php

namespace LaraCollab\TeamworkImport\Services\Persisters;

use LaraCollab\TeamworkImport\Services\Transformers\FileTransformer;

class AttachmentPersister extends BasePersister
{
    protected string $entityKey = 'files';

    protected string $modelBindingKey = 'attachment';

    protected string $teamworkType = 'file';

    protected string $transformerClass = FileTransformer::class;

    public function run(?array $entityFilter = null, ?int $projectId = null): array
    {
        $files = $this->apiClient->getFiles();
        $totalRecords = $files->count();
        $imported = 0;
        $skipped = [];

        foreach ($files as $fileData) {
            $fileId = $fileData['id'] ?? null;
            $attributes = $this->transform($fileData);

            $rawUserId = $attributes['user_id'] ?? null;

            if ($rawUserId !== null) {
                $attributes['user_id'] = $this->resolveLocalIdForType($rawUserId, 'user')
                    ?? $this->resolveOrCreatePlaceholderUser($rawUserId, $skipped);
            } else {
                $skipped[] = ['id' => $fileId, 'reason' => 'missing_user'];
                continue;
            }

            $versionTasks = $fileData['version']['tasks'] ?? [];

            if (empty($versionTasks)) {
                $skipped[] = ['id' => $fileId, 'reason' => 'no_related_tasks'];
                continue;
            }

            foreach ($versionTasks as $taskId) {
                $localTaskId = $this->resolveLocalIdForType((int) $taskId, 'task');

                if ($localTaskId === null) {
                    $skipped[] = ['id' => $fileId, 'reason' => 'missing_task'];
                    continue;
                }

                unset($attributes['teamwork_id'], $attributes['project_id']);

                $clone = $attributes;
                $clone['task_id'] = $localTaskId;

                $attachment = $this->createModel($clone);
                $this->recordMapping((int) $fileData['id'], $attachment);
                $imported++;
            }
        }

        return ['imported' => $imported, 'fetched' => $totalRecords, 'skipped' => $skipped];
    }
}
