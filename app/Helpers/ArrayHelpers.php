<?php

namespace App\Helpers;

class ArrayHelpers
{
    public static function isIndexedArray(mixed $sample): bool
    {
        // If not an array, return false
        if (!is_array($sample)) return false;

        // An empty array can be considered indexed
        if (empty($sample)) return true;

        // Get all keys and check if they are a perfect sequence starting from 0
        return array_keys($sample) === range(0, count($sample) - 1);
    }

    public static function isKeyedArray(mixed $sample): bool
    {
        // If is an array, and not an indexed array, it must be keyed
        return is_array($sample) && !self::isIndexedArray($sample);
    }

    public static function isDeepArray(mixed $sample): bool
    {
        // If not an array, return false
        if (!is_array($sample)) return false;

        // test each value in the array
        foreach (array_values($sample) as $value) {
            if (is_array($value) || is_object($value)) return true;
        }

        // else, return false
        return false;
    }
}
