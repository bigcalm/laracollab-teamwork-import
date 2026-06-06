<?php

namespace LaraCollab\TeamworkImport\Services\Transformers;

class TimeEntryTransformer
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

        $minutes = $attributes['minutes'] ?? null;
        if ($minutes === null) {
            $hoursDecimal = $data['hoursDecimal'] ?? null;
            if ($hoursDecimal !== null) {
                $attributes['minutes'] = (int) round((float) $hoursDecimal * 60);
            }
        }

        return $attributes;
    }
}
