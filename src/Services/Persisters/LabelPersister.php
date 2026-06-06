<?php

namespace LaraCollab\TeamworkImport\Services\Persisters;

use LaraCollab\TeamworkImport\Services\Transformers\TagTransformer;

class LabelPersister extends BasePersister
{
    protected string $entityKey = 'tags';

    protected string $modelBindingKey = 'label';

    protected string $teamworkType = 'tag';

    protected string $transformerClass = TagTransformer::class;

    public function run(?array $entityFilter = null, ?int $projectId = null): array
    {
        $tags = $this->apiClient->getTags();
        $totalRecords = $tags->count();
        $imported = 0;
        $skipped = [];
        $rationalised = 0;

        foreach ($tags as $tagData) {
            $name = $tagData['name'] ?? '';

            if (empty($name)) {
                $skipped[] = ['id' => $tagData['id'] ?? null, 'reason' => 'empty_name'];
                continue;
            }

            $modelClass = $this->getModelClass();
            $existing = $modelClass::where('name', $name)->first();

            if ($existing) {
                $this->recordMapping((int) $tagData['id'], $existing);
                $imported++;
                $rationalised++;
                continue;
            }

            $attributes = $this->transform($tagData);
            unset($attributes['teamwork_id']);

            $label = $this->createModel($attributes);
            $this->recordMapping((int) $tagData['id'], $label);
            $imported++;
        }

        return ['imported' => $imported, 'fetched' => $totalRecords, 'skipped' => $skipped, 'rationalised' => $rationalised];
    }
}
