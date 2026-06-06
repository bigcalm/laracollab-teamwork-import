<?php

namespace LaraCollab\TeamworkImport\Tests\Unit;

use LaraCollab\TeamworkImport\Services\Transformers\CommentTransformer;
use PHPUnit\Framework\TestCase;

class CommentTransformerTest extends TestCase
{
    private array $fieldMap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fieldMap = [
            'id'             => 'teamwork_id',
            'body'           => 'content',
            'postedByUserId' => 'user_id',
            'postedBy'       => 'user_id',
            'personId'       => 'user_id',
            'authorId'       => 'user_id',
            'projectId'      => 'project_id',
            'objectId'       => 'object_id',
        ];
    }

    public function test_transform_with_postedByUserId(): void
    {
        $data = [
            'id' => 1,
            'body' => 'Great work!',
            'postedByUserId' => 5,
            'projectId' => 10,
            'objectId' => 100,
        ];

        $result = CommentTransformer::transform($data, $this->fieldMap);

        $this->assertSame(1, $result['teamwork_id']);
        $this->assertSame('Great work!', $result['content']);
        $this->assertSame(5, $result['user_id']);
        $this->assertSame(10, $result['project_id']);
        $this->assertSame(100, $result['object_id']);
    }

    public function test_transform_fallback_postedBy(): void
    {
        $data = [
            'id' => 1,
            'body' => 'Hello',
            'postedBy' => 8,
        ];

        $result = CommentTransformer::transform($data, $this->fieldMap);

        $this->assertSame(8, $result['user_id']);
    }

    public function test_transform_postedByUserId_takes_precedence(): void
    {
        $data = [
            'id' => 1,
            'body' => 'Test',
            'postedByUserId' => 1,
            'postedBy' => 2,
            'personId' => 3,
            'authorId' => 4,
        ];

        $result = CommentTransformer::transform($data, $this->fieldMap);

        $this->assertSame(1, $result['user_id']);
    }

    public function test_transform_no_user_id(): void
    {
        $data = [
            'id' => 1,
            'body' => 'Anonymous',
        ];

        $result = CommentTransformer::transform($data, $this->fieldMap);

        $this->assertArrayNotHasKey('user_id', $result);
    }
}
