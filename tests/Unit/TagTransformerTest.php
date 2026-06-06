<?php

namespace LaraCollab\TeamworkImport\Tests\Unit;

use LaraCollab\TeamworkImport\Services\Transformers\TagTransformer;
use PHPUnit\Framework\TestCase;

class TagTransformerTest extends TestCase
{
    public function test_transform(): void
    {
        $fieldMap = [
            'id'    => 'teamwork_id',
            'name'  => 'name',
            'color' => 'color',
        ];

        $data = [
            'id' => 1,
            'name' => 'bug',
            'color' => '#ff0000',
        ];

        $result = TagTransformer::transform($data, $fieldMap);

        $this->assertSame(1, $result['teamwork_id']);
        $this->assertSame('bug', $result['name']);
        $this->assertSame('#ff0000', $result['color']);
    }

    public function test_transform_skips_null_color(): void
    {
        $fieldMap = [
            'id'    => 'teamwork_id',
            'color' => 'color',
        ];

        $result = TagTransformer::transform(['id' => 1], $fieldMap);

        $this->assertArrayNotHasKey('color', $result);
    }
}
