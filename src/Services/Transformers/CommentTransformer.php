<?php

namespace LaraCollab\TeamworkImport\Services\Transformers;

class CommentTransformer
{
    public static function transform(array $data, array $fieldMap): array
    {
        $attributes = [];

        foreach ($fieldMap as $apiField => $localField) {
            $value = $data[$apiField] ?? null;

            if ($value === null) {
                continue;
            }

            if (array_key_exists($localField, $attributes)) {
                continue;
            }

            $attributes[$localField] = $value;
        }

        return $attributes;
    }
}
