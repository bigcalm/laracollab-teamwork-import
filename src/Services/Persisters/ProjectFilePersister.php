<?php

namespace LaraCollab\TeamworkImport\Services\Persisters;

use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\Transformers\FileTransformer;

class ProjectFilePersister extends BasePersister
{
    protected string $entityKey = 'files';

    protected string $modelBindingKey = 'attachment';

    protected string $teamworkType = 'file';

    protected string $transformerClass = FileTransformer::class;

    public function __construct(
        ImportRun $importRun,
        ApiClient $apiClient,
        IdMappingService $idMappingService,
        ?string $role = null,
        private ?array $filterProjectIds = null,
        private ?\Closure $onProgress = null,
    ) {
        parent::__construct($importRun, $apiClient, $idMappingService, $role);
    }

    public function run(?array $entityFilter = null, ?int $projectId = null): array
    {
        $imported = 0;
        $skipped = [];

        $projectMappings = $this->resolveProjectMappings();
        $total = count($projectMappings);

        foreach ($projectMappings as $i => $projectMapping) {
            $teamworkProjectId = $projectMapping['teamwork_id'];
            $localProject = $projectMapping['local_model'];

            $projectName = $localProject?->name ?? "Project #{$teamworkProjectId}";
            $companyName = $localProject?->clientCompany?->name ?? '';

            if ($this->onProgress) {
                call_user_func(
                    $this->onProgress,
                    $companyName ? "{$companyName} / {$projectName}" : $projectName,
                    $i + 1,
                    $total,
                );
            }

            $files = $this->apiClient->getProjectFiles($teamworkProjectId);
            $taskFileMap = $this->buildTaskFileMap($teamworkProjectId);

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

                $linkedTaskIds = $taskFileMap[$fileId] ?? [];

                if (empty($linkedTaskIds)) {
                    $skipped[] = ['id' => $fileId, 'reason' => 'no_related_tasks'];
                    continue;
                }

                $mappingRecorded = false;

                $attributes['type'] = $this->resolveFileType($fileData['originalName'] ?? '');

                foreach ($linkedTaskIds as $linkedTeamworkTaskId) {
                    $localTaskId = $this->resolveLocalIdForType($linkedTeamworkTaskId, 'task');

                    if ($localTaskId === null) {
                        $skipped[] = ['id' => $fileId, 'reason' => 'missing_task'];
                        continue;
                    }

                    unset($attributes['teamwork_id'], $attributes['project_id']);

                    $clone = $attributes;
                    $clone['task_id'] = $localTaskId;

                    $attachment = $this->createModel($clone);

                    if (! $mappingRecorded) {
                        $this->recordMapping((int) $fileData['id'], $attachment);
                        $mappingRecorded = true;
                    }

                    $imported++;
                }
            }
        }

        return ['imported' => $imported, 'fetched' => 0, 'skipped' => $skipped];
    }

    private function resolveProjectMappings(): array
    {
        $query = \LaraCollab\TeamworkImport\Models\IdMapping::where('teamwork_type', 'project')
            ->where('import_run_id', $this->importRun->getKey());

        if ($this->filterProjectIds !== null) {
            $query->whereIn('teamwork_id', $this->filterProjectIds);
        }

        return $query->get()->map(function ($mapping) {
            return [
                'teamwork_id' => (int) $mapping->teamwork_id,
                'local_id' => $mapping->local_id,
                'local_model' => $mapping->local,
            ];
        })->values()->all();
    }

    private function buildTaskFileMap(int $teamworkProjectId): array
    {
        $tasks = $this->apiClient->getTasks($teamworkProjectId);
        $fileMap = [];

        foreach ($tasks as $taskData) {
            $taskId = $taskData['id'] ?? null;
            $attachments = $taskData['attachments'] ?? [];

            if ($taskId === null || empty($attachments)) {
                continue;
            }

            foreach ($attachments as $attachment) {
                if (($attachment['type'] ?? null) === 'files') {
                    $fileId = (int) ($attachment['id'] ?? 0);
                    if ($fileId > 0) {
                        $fileMap[$fileId][] = (int) $taskId;
                    }
                }
            }
        }

        return $fileMap;
    }

    private function resolveFileType(string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'ico'];
        $docExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];
        $codeExts = ['php', 'js', 'ts', 'css', 'html', 'json', 'xml', 'sql'];

        if (in_array($extension, $imageExts, true)) {
            return 'image';
        }

        if (in_array($extension, $docExts, true)) {
            return 'document';
        }

        if (in_array($extension, $codeExts, true)) {
            return 'code';
        }

        return 'file';
    }
}
