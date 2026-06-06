<?php

namespace LaraCollab\TeamworkImport\Services\Persisters;

use LaraCollab\TeamworkImport\Services\Transformers\CommentTransformer;

class CommentPersister extends BasePersister
{
    protected string $entityKey = 'comments';

    protected string $modelBindingKey = 'comment';

    protected string $teamworkType = 'comment';

    protected string $transformerClass = CommentTransformer::class;

    public function run(?array $entityFilter = null, ?int $projectId = null): array
    {
        $comments = $this->apiClient->getComments();
        $totalRecords = $comments->count();
        $imported = 0;
        $skipped = [];

        foreach ($comments as $commentData) {
            $commentId = $commentData['id'] ?? null;
            $attributes = $this->transform($commentData);

            $rawUserId = $attributes['user_id'] ?? null;

            if ($rawUserId !== null) {
                $attributes['user_id'] = $this->resolveLocalIdForType($rawUserId, 'user')
                    ?? $this->resolveOrCreatePlaceholderUser($rawUserId, $skipped);
            }

            if ($commentData['objectType'] ?? null === 'task') {
                $attributes['task_id'] = $this->resolveLocalIdForType(
                    (int) ($commentData['objectId'] ?? 0) ?: null,
                    'task'
                );
            }

            if (($attributes['user_id'] ?? null) === null) {
                $skipped[] = ['id' => $commentId, 'reason' => 'missing_user'];
                continue;
            }

            if (($attributes['task_id'] ?? null) === null) {
                $skipped[] = ['id' => $commentId, 'reason' => 'missing_task'];
                continue;
            }

            unset($attributes['teamwork_id'], $attributes['object_id']);

            $comment = $this->createModel($attributes);
            $this->recordMapping((int) $commentData['id'], $comment);
            $imported++;
        }

        return ['imported' => $imported, 'fetched' => $totalRecords, 'skipped' => $skipped];
    }
}
