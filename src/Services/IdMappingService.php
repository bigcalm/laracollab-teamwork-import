<?php

namespace LaraCollab\TeamworkImport\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LaraCollab\TeamworkImport\Models\IdMapping;

class IdMappingService
{
    public function find(int $teamworkId, string $teamworkType): ?Model
    {
        $mapping = IdMapping::where('teamwork_id', $teamworkId)
            ->where('teamwork_type', $teamworkType)
            ->first();

        if ($mapping === null) {
            return null;
        }

        return $mapping->local;
    }

    public function findOrFail(int $teamworkId, string $teamworkType): Model
    {
        $model = $this->find($teamworkId, $teamworkType);

        if ($model === null) {
            throw new \RuntimeException("No local mapping found for Teamwork {$teamworkType} #{$teamworkId}");
        }

        return $model;
    }

    public function store(int $teamworkId, string $teamworkType, Model $localModel, int $importRunId): IdMapping
    {
        return IdMapping::firstOrCreate([
            'teamwork_id' => $teamworkId,
            'teamwork_type' => $teamworkType,
            'local_type' => $localModel->getMorphClass(),
        ], [
            'local_id' => $localModel->getKey(),
            'import_run_id' => $importRunId,
        ]);
    }

    public function bulkStore(Collection $mappings): void
    {
        IdMapping::insert($mappings->toArray());
    }
}
