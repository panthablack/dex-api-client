<?php

namespace App\Enums;

use \App\Helpers\EnumHelpers;
use App\Models\MigratedCase;
use App\Models\MigratedShallowCase;
use App\Models\DataMigration;
use App\Models\MigratedShallowSession;

enum ResourceType: string
{
    case CLIENT = 'CLIENT';
    case CASE = 'CASE';
    case SHALLOW_CASE = 'SHALLOW_CASE';
    case SHALLOW_SESSION = 'SHALLOW_SESSION';
    case SHALLOW_CLOSED_CASE = 'SHALLOW_CLOSED_CASE';
    case ENRICHED_CASE = 'ENRICHED_CASE';
    case CASE_CLIENT = 'CASE_CLIENT';
    case CLOSED_CASE = 'CLOSED_CASE';
    case SESSION = 'SESSION';
    case FULL_CLIENT = 'FULL_CLIENT';
    case FULL_CASE = 'FULL_CASE';
    case FULL_SESSION = 'FULL_SESSION';

    public const MIGRATABLE_RESOURCES = [
        ResourceType::CLIENT,
        ResourceType::CASE,
        ResourceType::SHALLOW_CASE,
        ResourceType::SHALLOW_CLOSED_CASE,
        ResourceType::SESSION,
        ResourceType::CLOSED_CASE,
        ResourceType::CASE_CLIENT,
    ];

    public const RESOURCE_DEPENDENCIES = [
        ResourceType::SESSION->value => ResourceType::CASE,
        ResourceType::CASE_CLIENT->value => ResourceType::CASE,
    ];

    public static function getDependentResourceTypes(): array
    {
        return [self::SESSION];
    }

    public static function getIndependentResourceTypes(): array
    {
        return [self::CLIENT, self::CASE, self::SHALLOW_CASE, self::SHALLOW_CLOSED_CASE];
    }

    public function getTableName(): string
    {
        if ($this === ResourceType::CLIENT) return 'migrated_clients';
        if ($this === ResourceType::CASE) return 'migrated_cases';
        if ($this === ResourceType::SHALLOW_CASE) return 'migrated_shallow_cases';
        if ($this === ResourceType::SHALLOW_CLOSED_CASE) return 'migrated_shallow_cases'; // Same table as SHALLOW_CASE
        if ($this === ResourceType::ENRICHED_CASE) return 'migrated_enriched_cases';
        if ($this === ResourceType::SESSION) return 'migrated_sessions';
        else throw new \Exception('Resource type not supported by getTableName');
    }

    public static function resolve(string | ResourceType $type): ResourceType
    {
        return EnumHelpers::resolveEnum(ResourceType::class, $type, true);
    }

    public static function hasDependency(ResourceType $type): bool
    {
        return in_array($type->value, array_keys(self::RESOURCE_DEPENDENCIES));
    }

    public static function resourcesAvailable(ResourceType $type): bool
    {
        if ($type === self::CASE) return MigratedCase::count() > 0;
        if ($type === self::SHALLOW_CASE) return MigratedShallowCase::count() > 0;
        else return false;
    }

    /**
     * Check if ENRICHED_CASE can be triggered
     * Requires a completed SHALLOW_CASE migration
     */
    public static function canEnrichCases(): bool
    {
        // Check for completed SHALLOW_CASE migration
        return DataMigration::where('resource_type', self::SHALLOW_CASE)
            ->where('status', \App\Enums\DataMigrationStatus::COMPLETED)
            ->exists();
    }

    /**
     * Check if ENRICHED_SESSIONS can be triggered
     * Requires shallow sessions to exist (generated from case data)
     */
    public static function canEnrichSessions(): bool
    {
        // Check if shallow sessions exist
        return MigratedShallowSession::count() > 0;
    }

    public static function isMigratable($type): bool
    {
        $resolvedType = self::resolve($type);

        // return false if not a migratable type
        if (!in_array($resolvedType, self::MIGRATABLE_RESOURCES)) return false;

        // if resource is dependent on another type and there are no resources available, return false
        if (self::hasDependency($resolvedType)) {
            if (!self::resourcesAvailable(self::RESOURCE_DEPENDENCIES[$resolvedType->value]))
                return false;
        }

        return true;
    }

    public static function getValues(): array
    {
        return EnumHelpers::getValues(self::class);
    }
}
