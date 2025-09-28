<?php

namespace App\Enums;

use \App\Helpers\EnumHelpers;

enum ResourceType: string
{
    case CLIENT = 'CLIENT';
    case CASE = 'CASE';
    case CASE_CLIENT = 'CASE_CLIENT';
    case CLOSED_CASE = 'CLOSED_CASE';
    case SESSION = 'SESSION';
    case FULL_CLIENT = 'FULL_CLIENT';
    case FULL_CASE = 'FULL_CASE';
    case FULL_SESSION = 'FULL_SESSION';

    public const MIGRATABLE_RESOURCES = [
        ResourceType::CLIENT,
        ResourceType::CASE,
        ResourceType::SESSION,
        ResourceType::CLOSED_CASE,
        ResourceType::CASE_CLIENT,
    ];

    public static function getDependentResourceTypes(): array
    {
        return [self::SESSION];
    }

    public static function getIndependentResourceTypes(): array
    {
        return [self::CLIENT, self::CASE];
    }

    public function getTableName(): string
    {
        if ($this === ResourceType::CLIENT) return 'migrated_clients';
        if ($this === ResourceType::CASE) return 'migrated_cases';
        if ($this === ResourceType::SESSION) return 'migrated_sessions';
        else throw new \Exception('Resource type not supported by getTableName');
    }

    public static function resolve(string | ResourceType $type): ResourceType
    {
        return EnumHelpers::resolveEnum(ResourceType::class, $type, true);
    }

    public static function isMigratable($type): bool
    {
        $resolvedType = self::resolve($type);
        return in_array($resolvedType, self::MIGRATABLE_RESOURCES);
    }

    public static function getValues(): array
    {
        return EnumHelpers::getValues(self::class);
    }
}
