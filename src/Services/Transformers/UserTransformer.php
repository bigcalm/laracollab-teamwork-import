<?php

namespace LaraCollab\TeamworkImport\Services\Transformers;

class UserTransformer
{
    public static function transform(array $data, array $fieldMap): array
    {
        $attributes = [];

        foreach ($fieldMap as $apiField => $localField) {
            $value = $data[$apiField] ?? null;

            if ($value === null) {
                continue;
            }

            if ($apiField === 'userRate') {
                $value = (int) $value;
            }

            if (array_key_exists($localField, $attributes)) {
                continue;
            }

            $attributes[$localField] = $value;
        }

        $attributes['name'] = trim(
            ($data['firstName'] ?? '') . ' ' . ($data['lastName'] ?? '')
        );

        return $attributes;
    }
}
