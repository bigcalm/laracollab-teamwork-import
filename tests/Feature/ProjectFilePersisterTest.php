<?php

namespace LaraCollab\TeamworkImport\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraCollab\TeamworkImport\Models\IdMapping;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\Persisters\ProjectFilePersister;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\Project;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\Task;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\TaskGroup;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\User;
use LaraCollab\TeamworkImport\Tests\TestCase;

class ProjectFilePersisterTest extends TestCase
{
    public function test_imports_files_linked_to_tasks(): void
    {
        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        $project = Project::create(['name' => 'Test']);
        $taskGroup = TaskGroup::create(['name' => 'Backend', 'project_id' => $project->id, 'color' => '#000', 'order_column' => 1]);
        $user = User::create(['name' => 'John', 'email' => 'john@example.com', 'password' => 'hash']);

        $task10 = Task::create([
            'name' => 'DB Setup', 'project_id' => $project->id, 'group_id' => $taskGroup->id,
            'number' => 1, 'order_column' => 1,
        ]);
        $task11 = Task::create([
            'name' => 'Design', 'project_id' => $project->id, 'group_id' => $taskGroup->id,
            'number' => 2, 'order_column' => 2,
        ]);

        IdMapping::create([
            'teamwork_id' => 1, 'teamwork_type' => 'project',
            'local_id' => $project->id, 'local_type' => (new Project)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);
        IdMapping::create([
            'teamwork_id' => 1, 'teamwork_type' => 'user',
            'local_id' => $user->id, 'local_type' => (new User)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);
        IdMapping::create([
            'teamwork_id' => 10, 'teamwork_type' => 'task',
            'local_id' => $task10->id, 'local_type' => (new Task)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);
        IdMapping::create([
            'teamwork_id' => 11, 'teamwork_type' => 'task',
            'local_id' => $task11->id, 'local_type' => (new Task)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/projects/1/files.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/project_files.json'), true)
            ),
            'test.teamwork.com/projects/api/v3/projects/1/tasks.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/project_tasks_with_attachments.json'), true)
            ),
        ]);

        $persister = new ProjectFilePersister($importRun, new ApiClient, new IdMappingService);
        $result = $persister->run();

        $this->assertSame(3, $result['imported']);

        $this->assertDatabaseHas('attachments', [
            'name' => 'screenshot.png',
            'task_id' => $task10->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('attachments', [
            'name' => 'screenshot.png',
            'task_id' => $task11->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('attachments', [
            'name' => 'report.pdf',
            'task_id' => $task11->id,
        ]);

        $this->assertDatabaseMissing('attachments', [
            'name' => 'notes.txt',
        ]);
    }

    public function test_filters_by_project_ids(): void
    {
        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        $project1 = Project::create(['name' => 'Test 1']);
        $project2 = Project::create(['name' => 'Test 2']);

        IdMapping::create([
            'teamwork_id' => 1, 'teamwork_type' => 'project',
            'local_id' => $project1->id, 'local_type' => (new Project)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);
        IdMapping::create([
            'teamwork_id' => 2, 'teamwork_type' => 'project',
            'local_id' => $project2->id, 'local_type' => (new Project)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/projects/1/files.json*' => Http::response(
                json_encode(['files' => [], 'meta' => ['page' => ['hasMore' => false]]]),
                200
            ),
            'test.teamwork.com/projects/api/v3/projects/1/tasks.json*' => Http::response(
                json_encode(['tasks' => ['task' => []], 'meta' => ['page' => ['hasMore' => false]]]),
                200
            ),
        ]);

        $persister = new ProjectFilePersister($importRun, new ApiClient, new IdMappingService,
            filterProjectIds: [1],
        );
        $result = $persister->run();

        $this->assertSame(0, $result['imported']);
    }

    public function test_creates_placeholder_user(): void
    {
        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        $project = Project::create(['name' => 'Test']);
        $taskGroup = TaskGroup::create(['name' => 'Backend', 'project_id' => $project->id, 'color' => '#000', 'order_column' => 1]);
        $task = Task::create([
            'name' => 'DB Setup', 'project_id' => $project->id, 'group_id' => $taskGroup->id,
            'number' => 1, 'order_column' => 1,
        ]);

        IdMapping::create([
            'teamwork_id' => 1, 'teamwork_type' => 'project',
            'local_id' => $project->id, 'local_type' => (new Project)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);
        IdMapping::create([
            'teamwork_id' => 10, 'teamwork_type' => 'task',
            'local_id' => $task->id, 'local_type' => (new Task)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/projects/1/files.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/project_files.json'), true)
            ),
            'test.teamwork.com/projects/api/v3/projects/1/tasks.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/project_tasks_with_attachments.json'), true)
            ),
        ]);

        $persister = new ProjectFilePersister($importRun, new ApiClient, new IdMappingService);
        $persister->run();

        $this->assertDatabaseHas('users', [
            'email' => 'deleted-99@teamwork-import.local',
        ]);
    }
}
