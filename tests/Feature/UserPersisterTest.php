<?php

namespace LaraCollab\TeamworkImport\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\Persisters\UserPersister;
use LaraCollab\TeamworkImport\Tests\TestCase;

class UserPersisterTest extends TestCase
{
    private ImportRun $importRun;
    private UserPersister $persister;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);
        $apiClient = new ApiClient;
        $idMappingService = new IdMappingService;
        $this->persister = new UserPersister($this->importRun, $apiClient, $idMappingService);
    }

    public function test_imports_users(): void
    {
        Http::fake([
            'test.teamwork.com/projects/api/v3/people.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/users.json'), true)
            ),
        ]);

        $result = $this->persister->run();

        $this->assertSame(3, $result['fetched']);
        $this->assertSame(2, $result['imported']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame('missing_email', $result['skipped'][0]['reason']);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'job_title' => 'Senior Developer',
            'rate' => 5000,
            'phone' => '555-0101',
        ]);

        $this->assertDatabaseHas('teamwork_id_mappings', [
            'teamwork_id' => 1,
            'teamwork_type' => 'user',
        ]);
    }

    public function test_deduplicates_by_email(): void
    {
        \LaraCollab\TeamworkImport\Tests\Stubs\Models\User::create([
            'name' => 'Existing John',
            'email' => 'john.doe@example.com',
            'password' => 'hash',
        ]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/people.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/users.json'), true)
            ),
        ]);

        $result = $this->persister->run();

        $this->assertSame(2, $result['imported'], 'Existing user is counted as imported');
        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'name' => 'Existing John',
        ]);
    }
}
