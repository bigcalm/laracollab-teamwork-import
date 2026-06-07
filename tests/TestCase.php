<?php

namespace LaraCollab\TeamworkImport\Tests;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('teamwork.models', [
            'user'           => \LaraCollab\TeamworkImport\Tests\Stubs\Models\User::class,
            'client_company' => \LaraCollab\TeamworkImport\Tests\Stubs\Models\ClientCompany::class,
            'project'        => \LaraCollab\TeamworkImport\Tests\Stubs\Models\Project::class,
            'task_group'     => \LaraCollab\TeamworkImport\Tests\Stubs\Models\TaskGroup::class,
            'task'           => \LaraCollab\TeamworkImport\Tests\Stubs\Models\Task::class,
            'label'          => \LaraCollab\TeamworkImport\Tests\Stubs\Models\Label::class,
            'time_log'       => \LaraCollab\TeamworkImport\Tests\Stubs\Models\TimeLog::class,
            'comment'        => \LaraCollab\TeamworkImport\Tests\Stubs\Models\Comment::class,
            'attachment'     => \LaraCollab\TeamworkImport\Tests\Stubs\Models\Attachment::class,
            'task_priority'  => \LaraCollab\TeamworkImport\Tests\Stubs\Models\TaskPriority::class,
            'role'           => \LaraCollab\TeamworkImport\Tests\Stubs\Models\Role::class,
            'import_run'     => \LaraCollab\TeamworkImport\Models\ImportRun::class,
            'id_mapping'     => \LaraCollab\TeamworkImport\Models\IdMapping::class,
        ]);

        $app['config']->set('teamwork.api', [
            'base_url'        => 'https://test.teamwork.com/projects/api/v3',
            'token'           => 'test-token',
            'auth_mode'       => 'basic_token',
            'username'        => null,
            'password'        => null,
            'site_name'       => null,
            'timeout'         => 30,
            'connect_timeout' => 10,
            'page_size'       => 100,
        ]);

        $app['config']->set('teamwork.default_role', 'developer');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../src/Database/Migrations');

        $this->artisan('migrate', ['--database' => 'testbench'])->run();

        $this->createStubTables();
    }

    protected function createStubTables(): void
    {
        \Schema::connection('testbench')->create('users', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('job_title')->nullable();
            $table->decimal('rate', 10, 2)->nullable();
            $table->string('avatar')->nullable();
            $table->timestamps();
        });

        \Schema::connection('testbench')->create('client_companies', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('postal_code')->nullable();
            $table->timestamps();
        });

        \Schema::connection('testbench')->create('labels', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('color')->nullable();
            $table->timestamps();
        });

        \Schema::connection('testbench')->create('projects', function ($table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('client_company_id')->nullable()->constrained('client_companies');
            $table->timestamps();
        });

        \Schema::connection('testbench')->create('project_user', function ($table) {
            $table->foreignId('project_id')->constrained('projects');
            $table->foreignId('user_id')->constrained('users');
            $table->primary(['project_id', 'user_id']);
        });

        \Schema::connection('testbench')->create('task_groups', function ($table) {
            $table->id();
            $table->string('name');
            $table->foreignId('project_id')->nullable()->constrained('projects');
            $table->string('color')->nullable();
            $table->integer('order_column')->nullable();
            $table->timestamps();
        });

        \Schema::connection('testbench')->create('task_priorities', function ($table) {
            $table->id();
            $table->string('label');
            $table->timestamps();
        });

        \Schema::connection('testbench')->create('tasks', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->decimal('estimation', 10, 2)->nullable();
            $table->foreignId('group_id')->nullable()->constrained('task_groups');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users');
            $table->foreignId('project_id')->nullable()->constrained('projects');
            $table->foreignId('client_company_id')->nullable()->constrained('client_companies');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users');
            $table->foreignId('priority_id')->nullable()->constrained('task_priorities');
            $table->integer('number')->nullable();
            $table->integer('order_column')->nullable();
            $table->timestamps();
        });

        \Schema::connection('testbench')->create('client_company', function ($table) {
            $table->foreignId('client_id')->constrained('users');
            $table->foreignId('client_company_id')->constrained('client_companies');
            $table->primary(['client_id', 'client_company_id']);
        });

        \Schema::connection('testbench')->create('label_task', function ($table) {
            $table->foreignId('label_id')->constrained('labels');
            $table->foreignId('task_id')->constrained('tasks');
            $table->primary(['label_id', 'task_id']);
        });

        \Schema::connection('testbench')->create('subscribe_task', function ($table) {
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('task_id')->constrained('tasks');
        });

        \Schema::connection('testbench')->create('time_logs', function ($table) {
            $table->id();
            $table->integer('minutes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('task_id')->nullable()->constrained('tasks');
            $table->foreignId('project_id')->nullable()->constrained('projects');
            $table->timestamps();
        });

        \Schema::connection('testbench')->create('comments', function ($table) {
            $table->id();
            $table->text('content')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('task_id')->nullable()->constrained('tasks');
            $table->foreignId('project_id')->nullable()->constrained('projects');
            $table->timestamps();
        });

        \Schema::connection('testbench')->create('attachments', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->bigInteger('size')->nullable();
            $table->string('path')->nullable();
            $table->string('type', 255)->default('file');
            $table->foreignId('task_id')->constrained('tasks');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    protected function getPackageProviders($app): array
    {
        return [
            \LaraCollab\TeamworkImport\TeamworkImportServiceProvider::class,
        ];
    }
}
