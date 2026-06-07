<?php

namespace LaraCollab\TeamworkImport\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraCollab\TeamworkImport\Models\IdMapping;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\Persisters\TaskPersister;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\Label;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\Project;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\TaskGroup;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\TaskPriority;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\User;
use LaraCollab\TeamworkImport\Tests\TestCase;

class TaskPersisterTest extends TestCase
{
    public function test_imports_tasks(): void
    {
        $project = Project::create(['name' => 'Test']);
        $taskGroup = TaskGroup::create(['name' => 'Backend', 'project_id' => $project->id, 'color' => '#000']);
        $user = User::create(['name' => 'John', 'email' => 'john@example.com', 'password' => 'hash']);
        $priority = TaskPriority::create(['label' => 'high']);
        $label = Label::create(['name' => 'bug', 'color' => '#ff0000']);

        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        IdMapping::create([
            'teamwork_id' => 1,
            'teamwork_type' => 'project',
            'local_id' => $project->id,
            'local_type' => (new Project)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        IdMapping::create([
            'teamwork_id' => 1,
            'teamwork_type' => 'tasklist',
            'local_id' => $taskGroup->id,
            'local_type' => (new TaskGroup)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        IdMapping::create([
            'teamwork_id' => 2,
            'teamwork_type' => 'tasklist',
            'local_id' => $taskGroup->id,
            'local_type' => (new TaskGroup)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        IdMapping::create([
            'teamwork_id' => 1,
            'teamwork_type' => 'user',
            'local_id' => $user->id,
            'local_type' => (new User)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        IdMapping::create([
            'teamwork_id' => 2,
            'teamwork_type' => 'user',
            'local_id' => $user->id,
            'local_type' => (new User)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        IdMapping::create([
            'teamwork_id' => 1,
            'teamwork_type' => 'tag',
            'local_id' => $label->id,
            'local_type' => (new Label)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/projects/1/tasks.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/tasks.json'), true)
            ),
        ]);

        $persister = new TaskPersister($importRun, new ApiClient, new IdMappingService);
        $result = $persister->run();

        $this->assertSame(3, $result['fetched']);
        $this->assertSame(3, $result['imported']);

        $this->assertDatabaseHas('tasks', [
            'name' => 'Set up database',
            'estimation' => 8.0,
            'group_id' => $taskGroup->id,
            'assigned_to_user_id' => $user->id,
            'project_id' => $project->id,
            'number' => 1,
        ]);

        $task = \LaraCollab\TeamworkImport\Tests\Stubs\Models\Task::where('name', 'Set up database')->first();
        $this->assertTrue($task->labels()->where('label_id', $label->id)->exists());
        $this->assertTrue($task->subscribedUsers()->where('user_id', $user->id)->exists());
    }

    public function test_creates_placeholder_user_for_unknown_creator(): void
    {
        $project = Project::create(['name' => 'Test']);
        $taskGroup = TaskGroup::create(['name' => 'Backend', 'project_id' => $project->id, 'color' => '#000']);
        $user = User::create(['name' => 'John', 'email' => 'john@example.com', 'password' => 'hash']);

        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        IdMapping::create([
            'teamwork_id' => 1,
            'teamwork_type' => 'project',
            'local_id' => $project->id,
            'local_type' => (new Project)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        IdMapping::create([
            'teamwork_id' => 1,
            'teamwork_type' => 'user',
            'local_id' => $user->id,
            'local_type' => (new User)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        IdMapping::create([
            'teamwork_id' => 2,
            'teamwork_type' => 'user',
            'local_id' => $user->id,
            'local_type' => (new User)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/projects/1/tasks.json*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/tasks.json'), true)
            ),
        ]);

        $persister = new TaskPersister($importRun, new ApiClient, new IdMappingService);
        $result = $persister->run();

        $this->assertDatabaseHas('users', [
            'email' => 'deleted-99@teamwork-import.local',
            'name' => '[deleted user (99)]',
        ]);

        $hasPlaceholderSkip = false;
        foreach ($result['skipped'] as $s) {
            if ($s['reason'] === 'created_placeholder_user') {
                $hasPlaceholderSkip = true;
                break;
            }
        }
        $this->assertTrue($hasPlaceholderSkip);
    }

    public function test_creates_default_task_group_for_unmatched_tasklist(): void
    {
        $project = Project::create(['name' => 'Test']);
        $user = User::create(['name' => 'John', 'email' => 'john@example.com', 'password' => 'hash']);

        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        IdMapping::create([
            'teamwork_id' => 2,
            'teamwork_type' => 'project',
            'local_id' => $project->id,
            'local_type' => (new Project)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        IdMapping::create([
            'teamwork_id' => 1,
            'teamwork_type' => 'user',
            'local_id' => $user->id,
            'local_type' => (new User)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        IdMapping::create([
            'teamwork_id' => 2,
            'teamwork_type' => 'user',
            'local_id' => $user->id,
            'local_type' => (new User)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/projects/2/tasks.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/tasks.json'), true)
            ),
        ]);

        $persister = new TaskPersister($importRun, new ApiClient, new IdMappingService);
        $result = $persister->run();

        $this->assertDatabaseHas('task_groups', [
            'name' => 'Imported',
            'project_id' => $project->id,
        ]);

        $this->assertSame(3, $result['imported']);
    }
}
