<?php

namespace LaraCollab\TeamworkImport\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\Persisters\CompanyPersister;
use LaraCollab\TeamworkImport\Tests\TestCase;

class CompanyPersisterTest extends TestCase
{
    public function test_imports_companies(): void
    {
        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/companies.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/companies.json'), true)
            ),
        ]);

        $persister = new CompanyPersister($importRun, new ApiClient, new IdMappingService);
        $result = $persister->run();

        $this->assertSame(2, $result['fetched']);
        $this->assertSame(2, $result['imported']);

        $this->assertDatabaseHas('client_companies', [
            'name' => 'Acme Corp',
            'address' => '123 Main St',
            'postal_code' => '90210',
        ]);

        $this->assertDatabaseHas('teamwork_id_mappings', [
            'teamwork_id' => 1,
            'teamwork_type' => 'company',
        ]);
    }
}
