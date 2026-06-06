<?php

namespace LaraCollab\TeamworkImport\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Tests\TestCase;

class ImportTeamworkCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'test.teamwork.com/projects/api/v3/people.json*' => Http::response(
                json_decode(file_get_contents(__DIR__ . '/../Fixtures/users.json'), true)
            ),
            '*' => Http::response(['meta' => ['page' => ['hasMore' => false]]], 200),
        ]);
    }

    public function test_command_runs_successfully(): void
    {
        $this->artisan('teamwork:import', ['--entities' => 'users', '--no-interaction' => true])
            ->assertSuccessful();
    }

    public function test_command_resumes_partial_import(): void
    {
        ImportRun::create([
            'status' => 'partial',
            'entities_imported' => ['users' => 2],
            'started_at' => now()->subHour(),
        ]);

        $this->artisan('teamwork:import', ['--entities' => 'users,companies', '--no-interaction' => true])
            ->assertSuccessful();
    }

    public function test_command_records_api_failure_and_continues(): void
    {
        Http::fake([
            '*' => Http::response('', 500),
        ]);

        $this->artisan('teamwork:import', ['--entities' => 'users', '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('teamwork_import_runs', [
            'status' => 'completed',
        ]);
    }

    public function test_command_respects_role_option(): void
    {
        $this->artisan('teamwork:import', [
            '--entities' => 'users',
            '--role' => 'admin',
            '--no-interaction' => true,
        ])->assertSuccessful();
    }
}
