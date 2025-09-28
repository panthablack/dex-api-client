<?php

namespace App\Helpers;

class EnumHelpers
{
    public static function getCommonMutations(string $value): array
    {
        $values = [];
        $values[] = StringHelpers::camelCase($value);
        $values[] = StringHelpers::pascalCase($value);
        $values[] = StringHelpers::snakeCase($value);
        $values[] = StringHelpers::upperSnakeCase($value);
        $values[] = StringHelpers::kebabCase($value);
        $values[] = StringHelpers::upperKebabCase($value);
        $values[] = StringHelpers::sentenceCase($value);
        $values[] = StringHelpers::lowerSentenceCase($value);
        $values[] = StringHelpers::upperSentenceCase($value);
        $values[] = StringHelpers::titleCase($value);
        return $values;
    }

    public static function getEnumResolutionValues(object $case, bool $includePlurals = false): array
    {
        if (!self::isValidCase($case)) throw new \Exception("$case is not a valid case.");

        $values = [];

        // should match its name
        $name = $case->name;
        $values[] = $name;

        // should match its value (for unbacked cases this will be the same as the name)
        $value = self::getValue($case);
        $values[] = $value;

        // if a string, should match common mutations of the value
        if (is_string($value)) {
            array_push($values, ...self::getCommonMutations($value));

            // if plurals required, should match common mutations of the plural of the value
            if ($includePlurals) {
                $pluralValue = StringHelpers::getPlural($value);
                array_push($values, ...self::getCommonMutations($pluralValue));
            }
        }

        // remove duplicates while all elements are stringable
        $values = array_unique($values);

        // should match itself
        $values[] = $case;

        return $values;
    }

    public static function getValue(object $case): string | int
    {
        if (!self::isValidCase($case))
            throw new \Exception("$case is not a valid case.");
        else if (self::isBackedCase($case))
            return $case->value;
        else return $case->name;
    }

    public static function getValues(string $className): array
    {
        if (self::isValidEnum($className));
        return array_map(fn($c) => self::getValue($c), $className::cases());
    }

    public static function isValidEnum(string $className): bool
    {
        if (!class_exists($className)) return false;
        else if (!enum_exists($className)) return false;
        else return true;
    }

    public static function isBackedCase(object $case): bool
    {
        return $case instanceof \UnitEnum;
    }

    public static function isUnbackedCase(object $case): bool
    {
        return $case instanceof \BackedEnum;
    }

    public static function isValidCase(object $case): bool
    {
        return $case instanceof \BackedEnum || $case instanceof \UnitEnum;
    }

    public static function resolveCase(
        \UnitEnum | \BackedEnum $case,
        mixed $testValue,
        bool $includePlurals = false
    ): \UnitEnum | \BackedEnum | null {
        if (!self::isValidCase($case)) throw new \Exception("$case is not a valid case.");

        $resolutionValues = self::getEnumResolutionValues($case, $includePlurals);
        foreach ($resolutionValues as $resolutionValue) {
            if ($resolutionValue === $testValue) return $case;
        }
        // if nothing resolved, return null
        return null;
    }

    public static function resolveEnum(mixed $enum, mixed $testValue, bool $includePlurals = false): \UnitEnum | \BackedEnum
    {
        if (!self::isValidEnum($enum)) throw new \Exception("$enum is not a valid enum.");

        $cases = $enum::cases();

        foreach ($cases as $case) {
            $resolved = self::resolveCase($case, $testValue, $includePlurals);
            if ($resolved) return $resolved;
        }

        // if nothing resolved, throw error
        throw new \Exception("Could not resolve enum: $enum");
    }
}
