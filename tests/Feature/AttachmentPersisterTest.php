<?php

namespace LaraCollab\TeamworkImport\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraCollab\TeamworkImport\Models\IdMapping;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\Persisters\AttachmentPersister;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\Task;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\User;
use LaraCollab\TeamworkImport\Tests\TestCase;

class AttachmentPersisterTest extends TestCase
{
    public function test_imports_files_with_task_links(): void
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

        Http::fake([
            'test.teamwork.com/projects/api/v3/files.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/files.json'), true)
            ),
        ]);

        $persister = new AttachmentPersister($importRun, new ApiClient, new IdMappingService);
        $result = $persister->run();

        $this->assertSame(2, $result['fetched']);
        $this->assertSame(2, $result['imported']);

        $this->assertDatabaseHas('attachments', [
            'name' => 'screenshot.png',
            'task_id' => $task->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_skips_files_without_user(): void
    {
        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/files.json*' => Http::response(
                json_encode([
                    'files' => [
                        [
                            'id' => 999,
                            'originalName' => 'orphan.txt',
                            'uploadedBy' => null,
                        ],
                    ],
                    'meta' => ['page' => ['hasMore' => false]],
                ]),
                200
            ),
        ]);

        $persister = new AttachmentPersister($importRun, new ApiClient, new IdMappingService);
        $result = $persister->run();

        $this->assertSame(0, $result['imported']);
        $this->assertSame('missing_user', $result['skipped'][0]['reason']);
    }

    public function test_skips_files_without_task_links(): void
    {
        $user = User::create(['name' => 'John', 'email' => 'john@example.com', 'password' => 'hash']);
        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/files.json*' => Http::response(
                json_encode([
                    'files' => [
                        [
                            'id' => 999,
                            'originalName' => 'orphan.txt',
                            'uploadedBy' => 1,
                            'version' => [],
                        ],
                    ],
                    'meta' => ['page' => ['hasMore' => false]],
                ]),
                200
            ),
        ]);

        $persister = new AttachmentPersister($importRun, new ApiClient, new IdMappingService);
        $result = $persister->run();

        $this->assertSame(0, $result['imported']);

        $reasons = array_column($result['skipped'], 'reason');
        $this->assertContains('no_related_tasks', $reasons);
    }

    public function test_creates_placeholder_user(): void
    {
        $task = Task::create(['name' => 'DB Setup']);
        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        IdMapping::create([
            'teamwork_id' => 10,
            'teamwork_type' => 'task',
            'local_id' => $task->id,
            'local_type' => (new Task)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/files.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/files.json'), true)
            ),
        ]);

        $persister = new AttachmentPersister($importRun, new ApiClient, new IdMappingService);
        $persister->run();

        $this->assertDatabaseHas('users', [
            'email' => 'deleted-99@teamwork-import.local',
        ]);
    }
}
