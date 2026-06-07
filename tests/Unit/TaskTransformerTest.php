<?php

namespace LaraCollab\TeamworkImport\Tests\Unit;

use LaraCollab\TeamworkImport\Services\Transformers\TaskTransformer;
use PHPUnit\Framework\TestCase;

class TaskTransformerTest extends TestCase
{
    private array $fieldMap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fieldMap = [
            'id'               => 'teamwork_id',
            'name'             => 'name',
            'description'      => 'description',
            'estimateMinutes'  => 'estimation',
            'tasklistId'       => 'group_id',
            'assigneeUserIds'  => 'assigned_to_user_id',
            'priority'         => 'priority_id',
            'projectId'        => 'project_id',
            'companyId'        => 'client_company_id',
        ];
    }

    public function test_transform_all_fields(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Set up database',
            'description' => 'Create the schema',
            'estimateMinutes' => 480,
            'tasklistId' => 5,
            'assigneeUserIds' => [1],
            'projectId' => 10,
            'companyId' => 3,
        ];

        $result = TaskTransformer::transform($data, $this->fieldMap);

        $this->assertSame(1, $result['teamwork_id']);
        $this->assertSame('Set up database', $result['name']);
        $this->assertSame('Create the schema', $result['description']);
        $this->assertSame(8.0, $result['estimation']);
        $this->assertSame(5, $result['group_id']);
        $this->assertSame(1, $result['assigned_to_user_id']);
        $this->assertSame(10, $result['project_id']);
        $this->assertSame(3, $result['client_company_id']);
    }

    public function test_transform_estimate_minutes_to_hours(): void
    {
        $result = TaskTransformer::transform([
            'id' => 1,
            'estimateMinutes' => 240,
        ], $this->fieldMap);

        $this->assertSame(4.0, $result['estimation']);
    }

    public function test_transform_estimate_minutes_rounds_correctly(): void
    {
        $result = TaskTransformer::transform([
            'id' => 1,
            'estimateMinutes' => 90,
        ], $this->fieldMap);

        $this->assertSame(1.5, $result['estimation']);
    }

    public function test_transform_assignee_takes_first_user(): void
    {
        $result = TaskTransformer::transform([
            'id' => 1,
            'assigneeUserIds' => [5, 10, 15],
        ], $this->fieldMap);

        $this->assertSame(5, $result['assigned_to_user_id']);
    }

    public function test_transform_empty_assignees_returns_null(): void
    {
        $result = TaskTransformer::transform([
            'id' => 1,
            'assigneeUserIds' => [],
        ], $this->fieldMap);

        $this->assertArrayNotHasKey('assigned_to_user_id', $result);
    }

    public function test_transform_truncates_long_name(): void
    {
        $longName = str_repeat('a', 300);
        $result = TaskTransformer::transform([
            'id' => 1,
            'name' => $longName,
        ], $this->fieldMap);

        $this->assertSame(255, strlen($result['name']));
    }

    public function test_transform_skips_null_values(): void
    {
        $result = TaskTransformer::transform(['id' => 1], $this->fieldMap);

        $this->assertArrayNotHasKey('description', $result);
        $this->assertArrayNotHasKey('estimation', $result);
        $this->assertArrayNotHasKey('assigned_to_user_id', $result);
    }
}
