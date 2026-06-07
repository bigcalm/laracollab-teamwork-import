<?php

namespace LaraCollab\TeamworkImport\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\Project;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\Task;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\TaskGroup;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\User;
use LaraCollab\TeamworkImport\Tests\TestCase;

class ImportFilesCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $importRun = ImportRun::create(['status' => 'completed', 'started_at' => now(), 'completed_at' => now()]);

        $project = Project::create(['name' => 'Test']);
        $taskGroup = TaskGroup::create(['name' => 'Backend', 'project_id' => $project->id, 'color' => '#000', 'order_column' => 1]);
        $user = User::create(['name' => 'John', 'email' => 'john@example.com', 'password' => 'hash']);
        $task = Task::create([
            'name' => 'DB Setup', 'project_id' => $project->id, 'group_id' => $taskGroup->id,
            'number' => 1, 'order_column' => 1,
        ]);

        \LaraCollab\TeamworkImport\Models\IdMapping::create([
            'teamwork_id' => 1, 'teamwork_type' => 'project',
            'local_id' => $project->id, 'local_type' => (new Project)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);
        \LaraCollab\TeamworkImport\Models\IdMapping::create([
            'teamwork_id' => 1, 'teamwork_type' => 'user',
            'local_id' => $user->id, 'local_type' => (new User)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);
        \LaraCollab\TeamworkImport\Models\IdMapping::create([
            'teamwork_id' => 10, 'teamwork_type' => 'task',
            'local_id' => $task->id, 'local_type' => (new Task)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);
    }

    public function test_command_runs_successfully(): void
    {
        Http::fake([
            '*' => Http::response(['meta' => ['page' => ['hasMore' => false]]], 200),
        ]);

        $this->artisan('teamwork:import-files', ['--project' => '1', '--no-interaction' => true])
            ->assertSuccessful();
    }

    public function test_command_fails_without_import_run(): void
    {
        ImportRun::truncate();

        $this->artisan('teamwork:import-files', ['--no-interaction' => true])
            ->assertFailed();
    }

    public function test_command_fails_on_api_error(): void
    {
        Http::fake([
            '*' => Http::response('', 500),
        ]);

        $this->artisan('teamwork:import-files', ['--project' => '1', '--no-interaction' => true])
            ->assertFailed();
    }
}
