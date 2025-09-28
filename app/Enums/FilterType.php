<?php

namespace App\Enums;

use \App\Helpers\EnumHelpers;

enum FilterType: string
{
    case PAGE_INDEX = 'PAGE_INDEX';
    case PAGE_SIZE = 'PAGE_SIZE';
    case IS_ASCENDING = 'IS_ASCENDING';
    case SORT_COLUMN = 'SORT_COLUMN';
    case CREATED_DATE_FROM = 'CREATED_DATE_FROM';
    case CREATED_DATE_TO = 'CREATED_DATE_TO';
    case END_DATE_FROM = 'END_DATE_FROM';
    case END_DATE_TO = 'END_DATE_TO';

    public const CLIENT_FILTERS = [
        FilterType::PAGE_INDEX,
        FilterType::PAGE_SIZE,
        FilterType::IS_ASCENDING,
        FilterType::SORT_COLUMN,
        FilterType::CREATED_DATE_FROM,
        FilterType::CREATED_DATE_TO,
    ];

    public const CASE_FILTERS = [
        FilterType::PAGE_INDEX,
        FilterType::PAGE_SIZE,
        FilterType::IS_ASCENDING,
        FilterType::SORT_COLUMN,
        FilterType::CREATED_DATE_FROM,
        FilterType::CREATED_DATE_TO,
        FilterType::END_DATE_FROM,
        FilterType::END_DATE_TO,
    ];

    public const SESSION_FILTERS = [
        FilterType::PAGE_INDEX,
        FilterType::PAGE_SIZE,
        FilterType::IS_ASCENDING,
        FilterType::SORT_COLUMN,
        FilterType::CREATED_DATE_FROM,
        FilterType::CREATED_DATE_TO,
    ];

    public static function getDexFilter(string|FilterType $filter): string
    {
        $resolvedFilter = self::resolve($filter);
        if ($resolvedFilter === self::PAGE_INDEX) return 'PageIndex';
        if ($resolvedFilter === self::PAGE_SIZE) return 'PageSize';
        if ($resolvedFilter === self::IS_ASCENDING) return 'IsAscending';
        if ($resolvedFilter === self::SORT_COLUMN) return 'SortColumn';
        if ($resolvedFilter === self::CREATED_DATE_FROM) return 'CreatedDateFrom';
        if ($resolvedFilter === self::CREATED_DATE_TO) return 'CreatedDateTo';
        if ($resolvedFilter === self::END_DATE_FROM) return 'EndDateFrom';
        if ($resolvedFilter === self::END_DATE_TO) return 'EndDateTo';
        throw new \Exception('Filter type not supported for getDexFilter');
    }

    public static function resolve(string | FilterType $type): FilterType
    {
        return EnumHelpers::resolveEnum(FilterType::class, $type, true);
    }

    public static function getValues(): array
    {
        return EnumHelpers::getValues(self::class);
    }

    public static function isInvalidType(string | FilterType $type): bool
    {
        $resolved = self::resolve($type);
        if (!$resolved) throw "Could not resolve type $type";
        else return true;
    }
}
