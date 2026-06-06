<?php

namespace LaraCollab\TeamworkImport\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraCollab\TeamworkImport\Models\IdMapping;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\Persisters\CommentPersister;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\Task;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\User;
use LaraCollab\TeamworkImport\Tests\TestCase;

class CommentPersisterTest extends TestCase
{
    public function test_imports_task_comments(): void
    {
        $user = User::create(['name' => 'John', 'email' => 'john@example.com', 'password' => 'hash']);
        $task = Task::create(['name' => 'DB Setup']);

        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

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

        IdMapping::create([
            'teamwork_id' => 11,
            'teamwork_type' => 'task',
            'local_id' => $task->id,
            'local_type' => (new Task)->getMorphClass(),
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
            'test.teamwork.com/projects/api/v3/comments.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/comments.json'), true)
            ),
        ]);

        $persister = new CommentPersister($importRun, new ApiClient, new IdMappingService);
        $result = $persister->run();

        $this->assertSame(3, $result['fetched']);
        $this->assertSame(2, $result['imported']);

        $this->assertDatabaseHas('comments', [
            'content' => 'Looks good to me!',
            'user_id' => $user->id,
            'task_id' => $task->id,
        ]);
    }

    public function test_skips_non_task_comments(): void
    {
        $user = User::create(['name' => 'John', 'email' => 'john@example.com', 'password' => 'hash']);

        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        IdMapping::create([
            'teamwork_id' => 99,
            'teamwork_type' => 'user',
            'local_id' => $user->id,
            'local_type' => (new User)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/comments.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/comments.json'), true)
            ),
        ]);

        $persister = new CommentPersister($importRun, new ApiClient, new IdMappingService);
        $result = $persister->run();

        $hasMissingTask = false;
        foreach ($result['skipped'] as $s) {
            if ($s['reason'] === 'missing_task') {
                $hasMissingTask = true;
                break;
            }
        }
        $this->assertTrue($hasMissingTask);
    }
}
