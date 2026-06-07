<?php

namespace LaraCollab\TeamworkImport\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\ImportService;
use LaraCollab\TeamworkImport\Tests\TestCase;

class ImportServiceTest extends TestCase
{
    private ImportRun $importRun;
    private ImportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);
        $this->service = new ImportService(new ApiClient, new IdMappingService);
    }

    public function test_runs_entities_in_order(): void
    {
        Http::fake([
            '*' => Http::response(['meta' => ['page' => ['hasMore' => false]]], 200),
        ]);

        $phases = [];
        $entitiesSeen = [];

        $this->service->run(
            $this->importRun,
            entityFilter: ['users', 'companies', 'tags'],
            onProgress: function (string $phase, ?string $key) use (&$phases, &$entitiesSeen) {
                if ($phase === 'before') {
                    $phases[] = "before:{$key}";
                    $entitiesSeen[] = $key;
                }
                if ($phase === 'after') {
                    $phases[] = "after:{$key}";
                }
                if ($phase === 'done') {
                    $phases[] = 'done';
                }
            },
        );

        $this->assertContains('before:users', $phases);
        $this->assertContains('before:companies', $phases);
        $this->assertContains('before:tags', $phases);
        $this->assertContains('done', $phases);

        $this->assertSame(['companies', 'users', 'tags'], $entitiesSeen);
    }

    public function test_skips_entities(): void
    {
        Http::fake([
            '*' => Http::response(['meta' => ['page' => ['hasMore' => false]]], 200),
        ]);

        $entitiesSeen = [];

        $this->service->run(
            $this->importRun,
            entityFilter: ['users', 'companies'],
            skipEntities: ['users'],
            onProgress: function (string $phase, ?string $key) use (&$entitiesSeen) {
                if ($phase === 'before') {
                    $entitiesSeen[] = $key;
                }
            },
        );

        $this->assertNotContains('users', $entitiesSeen);
        $this->assertContains('companies', $entitiesSeen);
    }

    public function test_records_errors_and_continues(): void
    {
        Http::fake([
            '*' => Http::response('', 500),
        ]);

        $errors = [];

        $this->service->run(
            $this->importRun,
            entityFilter: ['users', 'companies'],
            onProgress: function (string $phase, ?string $key, ?string $label, int $n1, int $n2, mixed ...$extra) use (&$errors) {
                if ($phase === 'after') {
                    $error = $extra[1] ?? null;
                    if ($error) {
                        $errors[] = $error;
                    }
                }
                if ($phase === 'done') {
                    $e = $extra[1] ?? [];
                    foreach ($e as $err) {
                        $errors[] = $err['error'];
                    }
                }
            },
        );

        $this->assertNotEmpty($errors);
        $this->assertDatabaseHas('teamwork_import_runs', [
            'id' => $this->importRun->id,
            'status' => 'partial',
        ]);
    }

    public function test_tracks_imported_counts(): void
    {
        Http::fake([
            '*' => Http::response(['meta' => ['page' => ['hasMore' => false]]], 200),
        ]);

        $afterCounts = [];

        $this->service->run(
            $this->importRun,
            entityFilter: ['tags'],
            onProgress: function (string $phase, ?string $key, ?string $label, int $n1) use (&$afterCounts) {
                if ($phase === 'after') {
                    $afterCounts[$key] = $n1;
                }
            },
        );

        $this->assertArrayHasKey('tags', $afterCounts);
    }

    public function test_updates_import_run_after_each_entity(): void
    {
        Http::fake([
            '*' => Http::response(['meta' => ['page' => ['hasMore' => false]]], 200),
        ]);

        $this->service->run(
            $this->importRun,
            entityFilter: ['users'],
        );

        $this->importRun->refresh();
        $this->assertArrayHasKey('users', $this->importRun->entities_imported);
    }
}
