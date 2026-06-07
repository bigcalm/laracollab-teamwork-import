<?php

namespace LaraCollab\TeamworkImport\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LaraCollab\TeamworkImport\Models\ImportRun;
use LaraCollab\TeamworkImport\Services\ApiClient;
use LaraCollab\TeamworkImport\Services\IdMappingService;
use LaraCollab\TeamworkImport\Services\Persisters\CompanyPersister;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\ClientCompany;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\Project;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\Task;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\TaskGroup;
use LaraCollab\TeamworkImport\Tests\Stubs\Models\User;
use LaraCollab\TeamworkImport\Tests\TestCase;

class ApiClientPaginationTest extends TestCase
{
    public function test_handles_400_error_gracefully_after_first_page(): void
    {
        $importRun = ImportRun::create(['status' => 'running', 'started_at' => now()]);

        $project = Project::create(['name' => 'Test']);
        $taskGroup = TaskGroup::create(['name' => 'Backend', 'project_id' => $project->id, 'color' => '#000', 'order_column' => 1]);
        $user = User::create(['name' => 'John', 'email' => 'john@example.com', 'password' => 'hash']);
        $task = Task::create([
            'name' => 'DB Setup', 'project_id' => $project->id, 'group_id' => $taskGroup->id,
            'number' => 1, 'order_column' => 1,
        ]);

        \LaraCollab\TeamworkImport\Models\IdMapping::create([
            'teamwork_id' => 1, 'teamwork_type' => 'project',
            'local_id' => $project->id, 'local_type' => (new Project)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);
        \LaraCollab\TeamworkImport\Models\IdMapping::create([
            'teamwork_id' => 1, 'teamwork_type' => 'user',
            'local_id' => $user->id, 'local_type' => (new User)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);
        \LaraCollab\TeamworkImport\Models\IdMapping::create([
            'teamwork_id' => 10, 'teamwork_type' => 'task',
            'local_id' => $task->id, 'local_type' => (new Task)->getMorphClass(),
            'import_run_id' => $importRun->id,
        ]);

        Http::fake([
            'test.teamwork.com/projects/api/v3/projects/1/time.json*' => function ($request) {
                $url = $request->toPsrRequest()->getUri();
                parse_str($url->getQuery(), $params);
                $page = (int) ($params['page'] ?? 1);

                if ($page === 1) {
                    return Http::response(
                        json_encode([
                            'timelogs' => [
                                ['id' => 100, 'minutes' => 60, 'personId' => 1, 'taskId' => 10, 'projectId' => 1],
                            ],
                            'meta' => ['page' => ['hasMore' => true]],
                        ]),
                        200
                    );
                }

                return Http::response('', 400);
            },
        ]);

        $persister = new \LaraCollab\TeamworkImport\Services\Persisters\TimeLogPersister(
            $importRun, new ApiClient, new IdMappingService
        );
        $result = $persister->run();

        $this->assertSame(1, $result['fetched'], 'Should have fetched first page before 400');
        $this->assertSame(1, $result['imported']);
    }
}
