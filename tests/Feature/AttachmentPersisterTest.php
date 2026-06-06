<?php

namespace LaraCollab\TeamworkImport\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\Persisters\AttachmentPersister;
use LaraCollab\TeamworkImport\Tests\TestCase;

class AttachmentPersisterTest extends TestCase
{
    public function test_skips_all_files_due_to_undefined_taskIds(): void
    {
        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/files.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/files.json'), true)
            ),
        ]);

        $persister = new AttachmentPersister($importRun, new ApiClient, new IdMappingService);
        $result = $persister->run();

        $this->assertSame(2, $result['fetched']);
        $this->assertSame(0, $result['imported']);

        $reasons = array_column($result['skipped'], 'reason');
        $this->assertContains('no_related_tasks', $reasons);
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
}
