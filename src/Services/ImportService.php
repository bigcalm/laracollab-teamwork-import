<?php

namespace LaraCollab\TeamworkImport\Services;

use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\Persisters\AttachmentPersister;
use LaraCollab\TeamworkImport\Services\Persisters\CommentPersister;
use LaraCollab\TeamworkImport\Services\Persisters\CompanyPersister;
use LaraCollab\TeamworkImport\Services\Persisters\LabelPersister;
use LaraCollab\TeamworkImport\Services\Persisters\ProjectPersister;
use LaraCollab\TeamworkImport\Services\Persisters\TaskGroupPersister;
use LaraCollab\TeamworkImport\Services\Persisters\TaskPersister;
use LaraCollab\TeamworkImport\Services\Persisters\TimeLogPersister;
use LaraCollab\TeamworkImport\Services\Persisters\UserPersister;

class ImportService
{
    private array $persisterMap = [
        'users' => UserPersister::class,
        'companies' => CompanyPersister::class,
        'tags' => LabelPersister::class,
        'projects' => ProjectPersister::class,
        'tasklists' => TaskGroupPersister::class,
        'tasks' => TaskPersister::class,
        'time' => TimeLogPersister::class,
        'comments' => CommentPersister::class,
        'files' => AttachmentPersister::class,
    ];

    public function __construct(
        private ApiClient $apiClient,
        private IdMappingService $idMappingService,
    ) {}

    public function run(
        ImportRun $importRun,
        ?array $entityFilter = null,
        ?string $role = null,
        ?int $projectId = null,
        ?callable $onProgress = null,
        array $skipEntities = [],
    ): void {
        $entityOrder = config('teamwork.entity_order', []);
        $entitiesImported = [];
        $errors = [];
        $allSkipped = [];
        $allRationalised = [];

        $total = 0;

        foreach ($entityOrder as $entityKey) {
            if ($entityKey === 'project_people') continue;
            if (! isset($this->persisterMap[$entityKey])) continue;
            if ($entityFilter !== null && ! in_array($entityKey, $entityFilter)) continue;
            $total++;
        }

        foreach ($entityOrder as $entityKey) {
            if ($entityFilter !== null && ! in_array($entityKey, $entityFilter)) continue;
            if ($entityKey === 'project_people') continue;
            if (! isset($this->persisterMap[$entityKey])) continue;
            if (in_array($entityKey, $skipEntities, true)) continue;

            $label = $this->label($entityKey);

            if ($onProgress) {
                call_user_func($onProgress, 'before', $entityKey, $label, 0, $total);
            }

            $this->apiClient->setOnPageCallback(
                function (int $page, int $totalSoFar, ?int $totalPages = null) use ($onProgress, $entityKey, $label, $total) {
                    if ($onProgress) {
                        call_user_func($onProgress, 'page', $entityKey, $label, $page, $total, $totalSoFar, $totalPages);
                    }
                }
            );

            $persisterClass = $this->persisterMap[$entityKey];
            $persister = new $persisterClass(
                $importRun, $this->apiClient, $this->idMappingService, $role
            );

            $fetched = 0;
            $imported = 0;
            $skipped = [];
            $rationalised = 0;
            $error = null;

            try {
                $result = $persister->run($entityFilter, $projectId);

                if (is_array($result)) {
                    $imported = $result['imported'] ?? 0;
                    $fetched = $result['fetched'] ?? 0;
                    $skipped = $result['skipped'] ?? [];
                    $rationalised = $result['rationalised'] ?? 0;
                } else {
                    $imported = (int) $result;
                    $fetched = $imported;
                }

            } catch (\Throwable $e) {
                $errors[] = [
                    'entity' => $entityKey,
                    'error' => $e->getMessage(),
                ];
                $error = $e->getMessage();
            }

            $this->apiClient->setOnPageCallback(null);

            $entitiesImported[$entityKey] = $imported;
            $allSkipped[$entityKey] = $skipped;
            $allRationalised[$entityKey] = $rationalised;

            $importRun->update([
                'entities_imported' => $entitiesImported,
                'errors' => $errors,
                'status' => 'partial',
            ]);

            if ($onProgress) {
                call_user_func($onProgress, 'after', $entityKey, $label, $imported, $total, $fetched, $error, $skipped);
            }
        }

        if ($onProgress) {
            call_user_func($onProgress, 'done', null, null, 0, $total, $entitiesImported, $errors, $allSkipped, $allRationalised);
        }
    }

    private function label(string $entityKey): string
    {
        $labels = [
            'users' => 'Users',
            'companies' => 'Client Companies',
            'tags' => 'Labels',
            'projects' => 'Projects',
            'tasklists' => 'Task Groups',
            'tasks' => 'Tasks',
            'time' => 'Time Logs',
            'comments' => 'Comments',
            'files' => 'Attachments',
        ];

        return $labels[$entityKey] ?? $entityKey;
    }
}
