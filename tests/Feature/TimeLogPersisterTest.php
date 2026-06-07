<?php

namespace LaraCollab\TeamworkImport\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraCollab\TeamworkImport\Models\IdMapping;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\Persisters\TimeLogPersister;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\Project;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\Task;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\User;
use LaraCollab\TeamworkImport\Tests\TestCase;

class TimeLogPersisterTest extends TestCase
{
    private function setUpProjectMapping(ImportRun $importRun): void
    {
        $project = Project::create(['name' => 'Test Project']);
        IdMapping::create([
            'teamwork_id' => 1,
            'teamwork_type' => 'project',
            'local_id' => $project->id,
            'local_type' => (new Project)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);
    }

    public function test_imports_time_entries(): void
    {
        $user = User::create(['name' => 'John', 'email' => 'john@example.com', 'password' => 'hash']);
        $task = Task::create(['name' => 'DB Setup']);

        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);
        $this->setUpProjectMapping($importRun);

        IdMapping::create([
            'teamwork_id' => 1,
            'teamwork_type' => 'user',
            'local_id' => $user->id,
            'local_type' => (new User)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        IdMapping::create([
            'teamwork_id' => 10,
            'teamwork_type' => 'task',
            'local_id' => $task->id,
            'local_type' => (new Task)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/projects/1/time.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/time.json'), true)
            ),
        ]);

        $persister = new TimeLogPersister($importRun, new ApiClient, new IdMappingService);
        $result = $persister->run();

        $this->assertSame(5, $result['fetched']);
        $this->assertSame(3, $result['imported']);

        $reasons = array_column($result['skipped'], 'reason');
        $this->assertContains('missing_task', $reasons);
        $this->assertContains('created_placeholder_user', $reasons);

        $this->assertDatabaseHas('time_logs', [
            'minutes' => 60,
            'user_id' => $user->id,
            'task_id' => $task->id,
        ]);
    }

    public function test_creates_placeholder_user(): void
    {
        $task = Task::create(['name' => 'DB Setup']);
        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);
        $this->setUpProjectMapping($importRun);

        IdMapping::create([
            'teamwork_id' => 10,
            'teamwork_type' => 'task',
            'local_id' => $task->id,
            'local_type' => (new Task)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/projects/1/time.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/time.json'), true)
            ),
        ]);

        $persister = new TimeLogPersister($importRun, new ApiClient, new IdMappingService);
        $persister->run();

        $this->assertDatabaseHas('users', [
            'email' => 'deleted-99@teamwork-import.local',
        ]);
    }

    public function test_handles_hoursDecimal(): void
    {
        $user = User::create(['name' => 'John', 'email' => 'john@example.com', 'password' => 'hash']);
        $task = Task::create(['name' => 'DB Setup']);

        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);
        $this->setUpProjectMapping($importRun);

        IdMapping::create([
            'teamwork_id' => 1,
            'teamwork_type' => 'user',
            'local_id' => $user->id,
            'local_type' => (new User)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        IdMapping::create([
            'teamwork_id' => 10,
            'teamwork_type' => 'task',
            'local_id' => $task->id,
            'local_type' => (new Task)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/projects/1/time.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/time.json'), true)
            ),
        ]);

        $persister = new TimeLogPersister($importRun, new ApiClient, new IdMappingService);
        $persister->run();

        $this->assertDatabaseHas('time_logs', [
            'minutes' => 90,
            'task_id' => $task->id,
        ]);
    }

    public function test_invokes_project_progress_callback(): void
    {
        $task = Task::create(['name' => 'DB Setup']);
        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);
        $this->setUpProjectMapping($importRun);

        IdMapping::create([
            'teamwork_id' => 10,
            'teamwork_type' => 'task',
            'local_id' => $task->id,
            'local_type' => (new Task)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/projects/1/time.json*' => Http::response(
                json_encode(['timelogs' => [], 'meta' => ['page' => ['hasMore' => false]]]),
                200
            ),
        ]);

        $progress = [];
        $persister = new TimeLogPersister($importRun, new ApiClient, new IdMappingService);
        $persister->setOnProjectProgress(function (string $label, int $current, int $total) use (&$progress) {
            $progress = [$label, $current, $total];
        });
        $persister->run();

        $this->assertSame('Test Project', $progress[0]);
        $this->assertSame(1, $progress[1]);
        $this->assertSame(1, $progress[2]);
    }
}
