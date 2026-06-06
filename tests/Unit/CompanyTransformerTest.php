<?php

namespace LaraCollab\TeamworkImport\Tests\Unit;

use LaraCollab\TeamworkImport\Services\Transformers\CompanyTransformer;
use PHPUnit\Framework\TestCase;

class CompanyTransformerTest extends TestCase
{
    public function test_transform(): void
    {
        $fieldMap = [
            'id'         => 'teamwork_id',
            'name'       => 'name',
            'addressOne' => 'address',
            'zip'        => 'postal_code',
        ];

        $data = [
            'id' => 1,
            'name' => 'Acme Corp',
            'addressOne' => '123 Main St',
            'zip' => '90210',
        ];

        $result = CompanyTransformer::transform($data, $fieldMap);

        $this->assertSame(1, $result['teamwork_id']);
        $this->assertSame('Acme Corp', $result['name']);
        $this->assertSame('123 Main St', $result['address']);
        $this->assertSame('90210', $result['postal_code']);
    }

    public function test_transform_skips_null(): void
    {
        $fieldMap = [
            'id'         => 'teamwork_id',
            'addressOne' => 'address',
        ];

        $result = CompanyTransformer::transform(['id' => 1], $fieldMap);

        $this->assertArrayNotHasKey('address', $result);
        $this->assertSame(1, $result['teamwork_id']);
    }
}
