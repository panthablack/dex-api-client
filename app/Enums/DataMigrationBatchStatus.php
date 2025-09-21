<?php

namespace App\Enums;

enum DataMigrationBatchStatus: string
{
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case UNKNOWN = 'UNKNOWN';

    public static function resolve(string $type): DataMigrationBatchStatus
    {
        if (in_array($type, [
            self::IN_PROGRESS,
            self::IN_PROGRESS->value,
            'in_progress',
            'in-progress',
            'IN-PROGRESS',
        ])) return self::IN_PROGRESS;

        if (in_array($type, [
            self::COMPLETED,
            self::COMPLETED->value,
            'completed',
            'COMPLETED',
        ])) return self::COMPLETED;

        if (in_array($type, [
            self::FAILED,
            self::FAILED->value,
            'failed',
            'FAILED',
        ])) return self::FAILED;

        // If cannot resolve, return unknown
        return self::UNKNOWN;
    }
}
