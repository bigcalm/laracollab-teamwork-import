<?php

namespace LaraCollab\TeamworkImport\Tests\Unit;

use LaraCollab\TeamworkImport\Services\Transformers\FileTransformer;
use PHPUnit\Framework\TestCase;

class FileTransformerTest extends TestCase
{
    private array $fieldMap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fieldMap = [
            'id'              => 'teamwork_id',
            'originalName'    => 'name',
            'description'     => 'description',
            'size'            => 'size',
            'downloadURL'     => 'path',
            'projectId'       => 'project_id',
            'uploadedBy'      => 'user_id',
            'uploadedByUserID' => 'user_id',
        ];
    }

    public function test_transform_with_uploadedBy(): void
    {
        $data = [
            'id' => 1,
            'originalName' => 'photo.jpg',
            'description' => 'A photo',
            'size' => 102400,
            'downloadURL' => '/files/1/download',
            'projectId' => 5,
            'uploadedBy' => 3,
        ];

        $result = FileTransformer::transform($data, $this->fieldMap);

        $this->assertSame(1, $result['teamwork_id']);
        $this->assertSame('photo.jpg', $result['name']);
        $this->assertSame('A photo', $result['description']);
        $this->assertSame(102400, $result['size']);
        $this->assertSame('/files/1/download', $result['path']);
        $this->assertSame(5, $result['project_id']);
        $this->assertSame(3, $result['user_id']);
    }

    public function test_transform_fallback_uploadedByUserID(): void
    {
        $data = [
            'id' => 1,
            'originalName' => 'doc.pdf',
            'uploadedByUserID' => 7,
        ];

        $result = FileTransformer::transform($data, $this->fieldMap);

        $this->assertSame(7, $result['user_id']);
    }

    public function test_transform_uploadedBy_takes_precedence(): void
    {
        $data = [
            'id' => 1,
            'originalName' => 'file.txt',
            'uploadedBy' => 1,
            'uploadedByUserID' => 2,
        ];

        $result = FileTransformer::transform($data, $this->fieldMap);

        $this->assertSame(1, $result['user_id']);
    }
}
