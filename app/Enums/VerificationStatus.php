<?php

namespace App\Enums;

use \App\Helpers\EnumHelpers;

enum VerificationStatus: string
{
    case PENDING = 'PENDING';
    case VERIFIED = 'VERIFIED';
    case FAILED = 'FAILED';

    public static function getValues(): array
    {
        return EnumHelpers::getValues(self::class);
    }

    public static function resolve(string | VerificationStatus $type): VerificationStatus
    {
        return EnumHelpers::resolveEnum(VerificationStatus::class, $type, true);
    }
}
