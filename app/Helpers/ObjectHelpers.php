<?php

namespace App\Helpers;

class ObjectHelpers
{
    public static function toArray(object $object, bool $associative = true): array
    {
        return json_decode(json_encode($object), $associative);
    }
}
