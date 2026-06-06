<?php

namespace LaraCollab\TeamworkImport\Services\Persisters;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;

abstract class BasePersister
{
    protected ImportRun $importRun;

    protected ApiClient $apiClient;

    protected IdMappingService $idMappingService;

    protected string $entityKey;

    protected string $modelBindingKey;

    protected string $teamworkType;

    protected string $transformerClass;

    protected ?string $role = null;

    public function __construct(
        ImportRun $importRun,
        ApiClient $apiClient,
        IdMappingService $idMappingService,
        ?string $role = null
    ) {
        $this->importRun = $importRun;
        $this->apiClient = $apiClient;
        $this->idMappingService = $idMappingService;
        $this->role = $role;
    }

    abstract public function run(?array $entityFilter = null, ?int $projectId = null): array;

    protected function getModelClass(): string
    {
        return config("teamwork.models.{$this->modelBindingKey}");
    }

    protected function getConfig(): array
    {
        return config("teamwork.entities.{$this->entityKey}");
    }

    protected function getFieldMap(): array
    {
        return $this->getConfig()['field_map'] ?? [];
    }

    protected function transform(array $data): array
    {
        $class = $this->transformerClass;

        return $class::transform($data, $this->getFieldMap());
    }

    protected function recordMapping(int $teamworkId, $localModel): void
    {
        $this->idMappingService->store(
            $teamworkId,
            $this->teamworkType,
            $localModel,
            $this->importRun->getKey()
        );
    }

    protected function resolveLocalIdForType(?int $teamworkId, string $teamworkType): ?int
    {
        if ($teamworkId === null) {
            return null;
        }

        $model = $this->idMappingService->find($teamworkId, $teamworkType);

        return $model?->getKey();
    }

    protected function createModel(array $attributes): mixed
    {
        $modelClass = $this->getModelClass();

        return $modelClass::withoutEvents(function () use ($modelClass, $attributes) {
            return $modelClass::create($attributes);
        });
    }

    protected function resolveOrCreatePlaceholderUser(int $teamworkId, array &$skipped): int
    {
        $existing = $this->idMappingService->find($teamworkId, 'user');
        if ($existing) {
            return $existing->getKey();
        }

        $modelClass = config('teamwork.models.user');
        $email = "deleted-{$teamworkId}@teamwork-import.local";

        $existingUser = $modelClass::where('email', $email)->first();
        if ($existingUser) {
            $this->idMappingService->store($teamworkId, 'user', $existingUser, $this->importRun->getKey());
            $skipped[] = ['id' => $teamworkId, 'reason' => 'created_placeholder_user'];

            return $existingUser->getKey();
        }

        $user = $modelClass::withoutEvents(function () use ($modelClass, $email, $teamworkId) {
            return $modelClass::create([
                'name' => "[deleted user ({$teamworkId})]",
                'email' => $email,
                'password' => \Illuminate\Support\Facades\Hash::make(\Str::random(32)),
            ]);
        });

        $this->idMappingService->store($teamworkId, 'user', $user, $this->importRun->getKey());
        $skipped[] = ['id' => $teamworkId, 'reason' => 'created_placeholder_user'];

        return $user->getKey();
    }
}
