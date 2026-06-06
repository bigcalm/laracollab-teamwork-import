<?php

namespace LaraCollab\TeamworkImport\Tests\Unit;

use LaraCollab\TeamworkImport\Services\Transformers\UserTransformer;
use PHPUnit\Framework\TestCase;

class UserTransformerTest extends TestCase
{
    private array $fieldMap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fieldMap = [
            'id'        => 'teamwork_id',
            'firstName' => 'first_name',
            'lastName'  => 'last_name',
            'email'     => 'email',
            'phone'     => 'phone',
            'title'     => 'job_title',
            'userRate'  => 'rate',
            'avatarUrl' => 'avatar',
        ];
    }

    public function test_transform_all_fields(): void
    {
        $data = [
            'id' => 1,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '555-0101',
            'title' => 'Developer',
            'userRate' => 5000,
            'avatarUrl' => 'https://example.com/avatar.jpg',
        ];

        $result = UserTransformer::transform($data, $this->fieldMap);

        $this->assertSame(1, $result['teamwork_id']);
        $this->assertSame('John', $result['first_name']);
        $this->assertSame('Doe', $result['last_name']);
        $this->assertSame('john@example.com', $result['email']);
        $this->assertSame('555-0101', $result['phone']);
        $this->assertSame('Developer', $result['job_title']);
        $this->assertSame(5000, $result['rate']);
        $this->assertSame('https://example.com/avatar.jpg', $result['avatar']);
        $this->assertSame('John Doe', $result['name']);
    }

    public function test_transform_concatenates_name(): void
    {
        $result = UserTransformer::transform([
            'firstName' => 'Jane',
            'lastName' => 'Smith',
        ], $this->fieldMap);

        $this->assertSame('Jane Smith', $result['name']);
    }

    public function test_transform_name_handles_missing_parts(): void
    {
        $result = UserTransformer::transform([
            'firstName' => 'Bob',
        ], $this->fieldMap);

        $this->assertSame('Bob', trim($result['name']));
    }

    public function test_transform_first_non_null_field_wins(): void
    {
        $data = [
            'id' => 1,
            'userRate' => 6000,
        ];

        $result = UserTransformer::transform($data, $this->fieldMap);

        $this->assertSame(1, $result['teamwork_id']);
        $this->assertSame(6000, $result['rate']);
    }

    public function test_transform_null_values_skipped(): void
    {
        $result = UserTransformer::transform(['id' => 1], $this->fieldMap);

        $this->assertArrayNotHasKey('email', $result);
        $this->assertArrayNotHasKey('phone', $result);
        $this->assertArrayNotHasKey('job_title', $result);
        $this->assertArrayNotHasKey('avatar', $result);
    }

    public function test_transform_userRate_cast_to_int(): void
    {
        $result = UserTransformer::transform([
            'id' => 1,
            'userRate' => '5000',
        ], $this->fieldMap);

        $this->assertSame(5000, $result['rate']);
    }

    public function test_transform_skip_fields_not_in_data(): void
    {
        $result = UserTransformer::transform(['id' => 1], $this->fieldMap);

        $this->assertArrayNotHasKey('rate', $result);
        $this->assertArrayHasKey('teamwork_id', $result);
    }
}
