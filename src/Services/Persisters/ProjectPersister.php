<?php

namespace LaraCollab\TeamworkImport\Services\Persisters;

use LaraCollab\TeamworkImport\Services\Transformers\ProjectTransformer;

class ProjectPersister extends BasePersister
{
    protected string $entityKey = 'projects';

    protected string $modelBindingKey = 'project';

    protected string $teamworkType = 'project';

    protected string $transformerClass = ProjectTransformer::class;

    public function run(?array $entityFilter = null, ?int $projectId = null): array
    {
        $projects = $this->apiClient->getProjects();
        $totalRecords = $projects->count();
        $imported = 0;

        foreach ($projects as $projectData) {
            $attributes = $this->transform($projectData);

            if (isset($attributes['client_company_id'])) {
                $attributes['client_company_id'] = $this->resolveLocalIdForType(
                    $attributes['client_company_id'],
                    'company'
                );
            }

            unset($attributes['teamwork_id']);

            $project = $this->createModel($attributes);
            $this->recordMapping((int) $projectData['id'], $project);

            $this->syncProjectPeople((int) $projectData['id'], $project);
            $imported++;
        }

        return ['imported' => $imported, 'fetched' => $totalRecords];
    }

    private function syncProjectPeople(int $teamworkProjectId, $project): void
    {
        try {
            $people = $this->apiClient->getProjectPeople($teamworkProjectId);
        } catch (\Throwable) {
            return;
        }

        $userIds = [];

        foreach ($people as $person) {
            $localUser = $this->idMappingService->find((int) $person['id'], 'user');

            if ($localUser) {
                $userIds[] = $localUser->getKey();
            }
        }

        if (! empty($userIds)) {
            $project->users()->syncWithoutDetaching($userIds);
        }
    }
}
