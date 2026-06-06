<?php

namespace LaraCollab\TeamworkImport\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\Persisters\LabelPersister;
use LaraCollab\TeamworkImport\Tests\TestCase;

class LabelPersisterTest extends TestCase
{
    public function test_imports_labels(): void
    {
        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/tags.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/tags.json'), true)
            ),
        ]);

        $persister = new LabelPersister($importRun, new ApiClient, new IdMappingService);
        $result = $persister->run();

        $this->assertSame(3, $result['fetched']);
        $this->assertSame(2, $result['imported']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame('empty_name', $result['skipped'][0]['reason']);

        $this->assertDatabaseHas('labels', [
            'name' => 'bug',
            'color' => '#ff0000',
        ]);
    }

    public function test_rationalises_duplicates(): void
    {
        \LaraCollab\TeamworkImport\Tests\Stubs\Models\Label::create([
            'name' => 'bug',
            'color' => '#ff0000',
        ]);

        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/tags.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/tags.json'), true)
            ),
        ]);

        $persister = new LabelPersister($importRun, new ApiClient, new IdMappingService);
        $result = $persister->run();

        $this->assertSame(1, $result['rationalised']);
        $this->assertSame(2, \LaraCollab\TeamworkImport\Tests\Stubs\Models\Label::count(), 'existing bug + newly created feature');
    }
}
