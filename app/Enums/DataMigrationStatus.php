<?php

namespace App\Enums;

use \App\Helpers\EnumHelpers;

enum DataMigrationStatus: string
{
    case CANCELLED = 'CANCELLED';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case PENDING = 'PENDING';

    public static function resolve(string | DataMigrationStatus $type): DataMigrationStatus
    {
        return EnumHelpers::resolveEnum(DataMigrationStatus::class, $type, true);
    }

    public static function getValues(): array
    {
        return EnumHelpers::getValues(self::class);
    }
}
