<?php

namespace LaraCollab\TeamworkImport\Tests\Unit;

use LaraCollab\TeamworkImport\Services\Transformers\TimeEntryTransformer;
use PHPUnit\Framework\TestCase;

class TimeEntryTransformerTest extends TestCase
{
    private array $fieldMap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fieldMap = [
            'id'             => 'teamwork_id',
            'minutes'        => 'minutes',
            'personId'       => 'user_id',
            'userId'         => 'user_id',
            'loggedByUserId' => 'user_id',
            'taskId'         => 'task_id',
            'projectId'      => 'project_id',
        ];
    }

    public function test_transform_with_personId(): void
    {
        $data = [
            'id' => 1,
            'minutes' => 60,
            'personId' => 5,
            'taskId' => 10,
            'projectId' => 20,
        ];

        $result = TimeEntryTransformer::transform($data, $this->fieldMap);

        $this->assertSame(1, $result['teamwork_id']);
        $this->assertSame(60, $result['minutes']);
        $this->assertSame(5, $result['user_id']);
        $this->assertSame(10, $result['task_id']);
        $this->assertSame(20, $result['project_id']);
    }

    public function test_transform_fallback_userId(): void
    {
        $data = [
            'id' => 1,
            'minutes' => 30,
            'userId' => 8,
            'taskId' => 10,
        ];

        $result = TimeEntryTransformer::transform($data, $this->fieldMap);

        $this->assertSame(8, $result['user_id']);
    }

    public function test_transform_fallback_loggedByUserId(): void
    {
        $data = [
            'id' => 1,
            'minutes' => 45,
            'loggedByUserId' => 3,
            'taskId' => 10,
        ];

        $result = TimeEntryTransformer::transform($data, $this->fieldMap);

        $this->assertSame(3, $result['user_id']);
    }

    public function test_transform_personId_takes_precedence(): void
    {
        $data = [
            'id' => 1,
            'minutes' => 60,
            'personId' => 1,
            'userId' => 2,
            'loggedByUserId' => 3,
        ];

        $result = TimeEntryTransformer::transform($data, $this->fieldMap);

        $this->assertSame(1, $result['user_id']);
    }

    public function test_transform_minutes_from_hoursDecimal(): void
    {
        $data = [
            'id' => 1,
            'hoursDecimal' => 2.5,
            'personId' => 5,
            'taskId' => 10,
        ];

        $result = TimeEntryTransformer::transform($data, $this->fieldMap);

        $this->assertSame(150, $result['minutes']);
    }

    public function test_transform_hoursDecimal_ignored_when_minutes_present(): void
    {
        $data = [
            'id' => 1,
            'minutes' => 60,
            'hoursDecimal' => 2.5,
            'personId' => 5,
            'taskId' => 10,
        ];

        $result = TimeEntryTransformer::transform($data, $this->fieldMap);

        $this->assertSame(60, $result['minutes']);
    }
}
