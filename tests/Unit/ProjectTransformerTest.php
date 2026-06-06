<?php

namespace LaraCollab\TeamworkImport\Tests\Unit;

use LaraCollab\TeamworkImport\Services\Transformers\ProjectTransformer;
use PHPUnit\Framework\TestCase;

class ProjectTransformerTest extends TestCase
{
    public function test_transform(): void
    {
        $fieldMap = [
            'id'          => 'teamwork_id',
            'name'        => 'name',
            'description' => 'description',
            'companyId'   => 'client_company_id',
        ];

        $data = [
            'id' => 1,
            'name' => 'Website Redesign',
            'description' => 'Redesign the website',
            'companyId' => 5,
        ];

        $result = ProjectTransformer::transform($data, $fieldMap);

        $this->assertSame(1, $result['teamwork_id']);
        $this->assertSame('Website Redesign', $result['name']);
        $this->assertSame('Redesign the website', $result['description']);
        $this->assertSame(5, $result['client_company_id']);
    }

    public function test_transform_null_description(): void
    {
        $fieldMap = [
            'id'          => 'teamwork_id',
            'description' => 'description',
        ];

        $result = ProjectTransformer::transform(['id' => 1], $fieldMap);

        $this->assertArrayNotHasKey('description', $result);
    }
}
