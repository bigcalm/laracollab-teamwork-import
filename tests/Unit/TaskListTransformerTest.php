<?php

namespace LaraCollab\TeamworkImport\Tests\Unit;

use LaraCollab\TeamworkImport\Services\Transformers\TaskListTransformer;
use PHPUnit\Framework\TestCase;

class TaskListTransformerTest extends TestCase
{
    public function test_transform(): void
    {
        $fieldMap = [
            'id'        => 'teamwork_id',
            'name'      => 'name',
            'projectId' => 'project_id',
        ];

        $data = [
            'id' => 1,
            'name' => 'Backend',
            'projectId' => 5,
        ];

        $result = TaskListTransformer::transform($data, $fieldMap);

        $this->assertSame(1, $result['teamwork_id']);
        $this->assertSame('Backend', $result['name']);
        $this->assertSame(5, $result['project_id']);
    }

    public function test_transform_null_project_id(): void
    {
        $fieldMap = [
            'id'        => 'teamwork_id',
            'projectId' => 'project_id',
        ];

        $result = TaskListTransformer::transform(['id' => 1], $fieldMap);

        $this->assertArrayNotHasKey('project_id', $result);
    }
}
