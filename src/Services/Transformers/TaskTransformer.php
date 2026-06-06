<?php

namespace LaraCollab\TeamworkImport\Services\Transformers;

class TaskTransformer
{
    public static function transform(array $data, array $fieldMap): array
    {
        $attributes = [];

        foreach ($fieldMap as $apiField => $localField) {
            $value = $data[$apiField] ?? null;

            if ($value === null) {
                continue;
            }

            if ($apiField === 'name' && is_string($value)) {
                $value = mb_substr($value, 0, 255);
            }

            if ($apiField === 'estimateMinutes') {
                $value = round((float) $value / 60, 2);
            }

            if ($apiField === 'assigneeUserIds' && is_array($value)) {
                $value = ! empty($value) ? (int) $value[0] : null;
                if ($value === null) {
                    continue;
                }
            }

            if (array_key_exists($localField, $attributes)) {
                continue;
            }

            $attributes[$localField] = $value;
        }

        return $attributes;
    }
}
