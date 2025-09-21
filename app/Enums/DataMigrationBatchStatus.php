<?php

namespace App\Enums;

enum DataMigrationBatchStatus: string
{
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case PENDING = 'PENDING';
    case UNKNOWN = 'UNKNOWN';

    public static function resolve(string $type): DataMigrationBatchStatus
    {
        if (in_array($type, [
            self::IN_PROGRESS,
            self::IN_PROGRESS->value,
            'processing',
            'PROCESSING',
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

        if (in_array($type, [
            self::PENDING,
            self::PENDING->value,
            'pending',
            'PENDING',
        ])) return self::PENDING;

        // If cannot resolve, return unknown
        return self::UNKNOWN;
    }
}
