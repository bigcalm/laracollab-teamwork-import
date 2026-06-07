<?php

namespace LaraCollab\TeamworkImport\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\Persisters\UserPersister;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\ClientCompany;
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

        $reasons = array_column($result['skipped'], 'reason');
        $this->assertContains('missing_email', $reasons);
        $this->assertContains('client_user_unresolved_company', $reasons);

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

    public function test_syncs_client_user_to_company(): void
    {
        $company = ClientCompany::create(['name' => 'Acme Corp', 'address' => '123 Main St']);

        \LaraCollab\TeamworkImport\Models\IdMapping::create([
            'teamwork_id' => 1,
            'teamwork_type' => 'company',
            'local_id' => $company->id,
            'local_type' => (new ClientCompany)->getMorphClass(),
            'import_run_id' => $this->importRun->id,
        ]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/people.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/users.json'), true)
            ),
        ]);

        $result = $this->persister->run();

        $user = \LaraCollab\TeamworkImport\Tests\Stubs\Models\User::where('email', 'john.doe@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->clientCompanies()->where('client_company_id', $company->id)->exists());
    }

    public function test_skips_client_user_without_company_mapping(): void
    {
        Http::fake([
            'test.teamwork.com/projects/api/v3/people.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/users.json'), true)
            ),
        ]);

        $result = $this->persister->run();

        $hasUnresolvedCompany = false;
        foreach ($result['skipped'] as $s) {
            if ($s['reason'] === 'client_user_unresolved_company') {
                $hasUnresolvedCompany = true;
                break;
            }
        }
        $this->assertTrue($hasUnresolvedCompany);
    }
}
