<?php

namespace LaraCollab\TeamworkImport\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\Persisters\TaskGroupPersister;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\Project;
use LaraCollab\TeamworkImport\Tests\TestCase;

class TaskGroupPersisterTest extends TestCase
{
    public function test_imports_task_groups(): void
    {
        $project = Project::create(['name' => 'Test Project']);
        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        \LaraCollab\TeamworkImport\Models\IdMapping::create([
            'teamwork_id' => 1,
            'teamwork_type' => 'project',
            'local_id' => $project->id,
            'local_type' => (new Project)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/tasklists.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/tasklists.json'), true)
            ),
        ]);

        $persister = new TaskGroupPersister($importRun, new ApiClient, new IdMappingService);
        $result = $persister->run();

        $this->assertSame(3, $result['fetched']);
        $this->assertSame(2, $result['imported']);

        $this->assertDatabaseHas('task_groups', [
            'name' => 'Backend',
            'project_id' => $project->id,
            'color' => '#6b7280',
            'order_column' => 1,
        ]);
    }

    public function test_skips_task_groups_with_unresolved_project(): void
    {
        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/tasklists.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/tasklists.json'), true)
            ),
        ]);

        $persister = new TaskGroupPersister($importRun, new ApiClient, new IdMappingService);
        $result = $persister->run();

        $this->assertSame(0, $result['imported']);
    }
}
