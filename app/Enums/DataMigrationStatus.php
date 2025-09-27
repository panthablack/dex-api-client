<?php

namespace App\Enums;

enum DataMigrationStatus: string
{
    case CANCELLED = 'CANCELLED';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case PENDING = 'PENDING';
    case UNKNOWN = 'UNKNOWN';

    public static function resolve(string | DataMigrationStatus $type): DataMigrationStatus
    {
        if (in_array($type, [
            self::CANCELLED,
            self::CANCELLED->value,
            'cancelled',
            'CANCELLED',
        ])) return self::CANCELLED;

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
            self::IN_PROGRESS,
            self::IN_PROGRESS->value,
            'in_progress',
            'in-progress',
            'IN-PROGRESS',
        ])) return self::IN_PROGRESS;

        if (in_array($type, [
            self::PENDING,
            self::PENDING->value,
            'pending',
            'PENDING',
        ])) return self::PENDING;

        // If cannot resolve, return unknown
        return self::UNKNOWN;
    }

    public static function getValues(): array
    {
        return [
            self::CANCELLED->value,
            self::COMPLETED->value,
            self::FAILED->value,
            self::IN_PROGRESS->value,
            self::PENDING->value,
            self::UNKNOWN->value,
        ];
    }
}
