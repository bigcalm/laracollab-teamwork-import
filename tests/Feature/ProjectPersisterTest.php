<?php

namespace LaraCollab\TeamworkImport\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\Persisters\ProjectPersister;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\ClientCompany;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\User;
use LaraCollab\TeamworkImport\Tests\TestCase;

class ProjectPersisterTest extends TestCase
{
    public function test_imports_projects_with_company_and_people(): void
    {
        $company = ClientCompany::create(['name' => 'Acme Corp', 'address' => '123 Main St']);
        $user = User::create(['name' => 'John', 'email' => 'john@example.com', 'password' => 'hash']);

        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        \LaraCollab\TeamworkImport\Models\IdMapping::create([
            'teamwork_id' => 1,
            'teamwork_type' => 'company',
            'local_id' => $company->id,
            'local_type' => (new ClientCompany)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        \LaraCollab\TeamworkImport\Models\IdMapping::create([
            'teamwork_id' => 1,
            'teamwork_type' => 'user',
            'local_id' => $user->id,
            'local_type' => (new User)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/projects.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/projects.json'), true)
            ),
            'test.teamwork.com/projects/api/v3/projects/1/people.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/project_people.json'), true)
            ),
            'test.teamwork.com/projects/api/v3/projects/2/people.json*' => Http::response(
                \json_decode(\file_get_contents(__DIR__ . '/../Fixtures/project_people.json'), true)
            ),
        ]);

        $persister = new ProjectPersister($importRun, new ApiClient, new IdMappingService);
        $result = $persister->run();

        $this->assertSame(2, $result['fetched']);
        $this->assertSame(2, $result['imported']);

        $this->assertDatabaseHas('projects', [
            'name' => 'Website Redesign',
            'client_company_id' => $company->id,
        ]);

        $project = \LaraCollab\TeamworkImport\Tests\Stubs\Models\Project::where('name', 'Website Redesign')->first();
        $this->assertTrue($project->users()->where('user_id', $user->id)->exists());
    }
}
