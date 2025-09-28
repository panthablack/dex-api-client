<?php

namespace App\Enums;

use \App\Helpers\EnumHelpers;

enum DataMigrationBatchStatus: string
{
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case PENDING = 'PENDING';

    public static function resolve(string | DataMigrationBatchStatus $type): DataMigrationBatchStatus
    {
        return EnumHelpers::resolveEnum(DataMigrationBatchStatus::class, $type, true);
    }

    public static function getValues(): array
    {
        return EnumHelpers::getValues(self::class);
    }
}
