<?php

namespace LaraCollab\TeamworkImport\Tests\Unit;

use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\Persisters\BasePersister;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\User;
use LaraCollab\TeamworkImport\Tests\TestCase;

class BasePersisterTest extends TestCase
{
    private BasePersister $persister;
    private ImportRun $importRun;
    private ApiClient $apiClient;
    private IdMappingService $idMappingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importRun = $this->createMock(ImportRun::class);
        $this->importRun->method('getKey')->willReturn(1);

        $this->apiClient = $this->createMock(ApiClient::class);
        $this->idMappingService = $this->createMock(IdMappingService::class);

        $this->persister = new class (
            $this->importRun,
            $this->apiClient,
            $this->idMappingService,
        ) extends BasePersister {
            protected string $entityKey = 'users';
            protected string $modelBindingKey = 'user';
            protected string $teamworkType = 'user';
            protected string $transformerClass = 'LaraCollab\TeamworkImport\Services\Transformers\UserTransformer';

            public function run(?array $entityFilter = null, ?int $projectId = null): array
            {
                return [];
            }

            public function exposeCreateModel(array $attributes): mixed
            {
                return $this->createModel($attributes);
            }

            public function exposeRecordMapping(int $teamworkId, $localModel): void
            {
                $this->recordMapping($teamworkId, $localModel);
            }

            public function exposeResolveLocalIdForType(?int $teamworkId, string $teamworkType): ?int
            {
                return $this->resolveLocalIdForType($teamworkId, $teamworkType);
            }

            public function exposeGetModelClass(): string
            {
                return $this->getModelClass();
            }

            public function exposeGetFieldMap(): array
            {
                return $this->getFieldMap();
            }
        };
    }

    public function test_get_model_class_returns_config_value(): void
    {
        $this->assertSame(User::class, $this->persister->exposeGetModelClass());
    }

    public function test_get_field_map_returns_config_value(): void
    {
        $this->assertIsArray($this->persister->exposeGetFieldMap());
    }

    public function test_resolve_local_id_returns_null_for_null_input(): void
    {
        $this->assertNull($this->persister->exposeResolveLocalIdForType(null, 'user'));
    }

    public function test_resolve_local_id_calls_service(): void
    {
        $user = new User;
        $user->id = 5;
        $user->exists = true;

        $this->idMappingService
            ->expects($this->once())
            ->method('find')
            ->with(42, 'user')
            ->willReturn($user);

        $this->assertSame(5, $this->persister->exposeResolveLocalIdForType(42, 'user'));
    }

    public function test_resolve_local_id_returns_null_when_not_found(): void
    {
        $this->idMappingService
            ->expects($this->once())
            ->method('find')
            ->with(42, 'user')
            ->willReturn(null);

        $this->assertNull($this->persister->exposeResolveLocalIdForType(42, 'user'));
    }
}
