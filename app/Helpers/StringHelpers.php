<?php

namespace App\Helpers;

use \Illuminate\Support\Str;

class StringHelpers
{
    const SPACING_SYMBOLS = ['-', '_'];

    /**
     * Convert a string to camelCase
     */
    public static function camelCase(string $string): string
    {
        return lcfirst(self::pascalCase($string));
    }

    /**
     * Get plural value of a word
     */
    public static function getPlural(string $string): string
    {
        return Str::plural($string);
    }

    /**
     * Convert a string to kebab-case
     */
    public static function kebabCase(string $string): string
    {
        return self::lowerSpacerCase($string, '-');
    }

    /**
     * Convert a string to lower*spacer*case
     */
    public static function lowerSpacerCase(string $string, string $spacer): string
    {
        $string = self::titleCase($string);
        $string = implode($spacer, explode(' ', $string));
        return mb_strtolower($string, 'UTF-8');
    }

    /**
     * Convert a string to PascalCase
     */
    public static function pascalCase(string $string): string
    {
        return str_replace(' ', '', self::titleCase($string));
    }

    /**
     * Convert a string to snake_case
     */
    public static function snakeCase(string $string): string
    {
        return self::lowerSpacerCase($string, '_');
    }

    /**
     * Convert a string to camelCase
     */
    public static function removeSpacingSymbols(
        string $string,
        bool $leaveWhitespace = true
    ): string {
        return str_replace(self::SPACING_SYMBOLS, ($leaveWhitespace ? ' ' : ''), trim($string));
    }

    /**
     * Convert a string to Sentence case
     */
    public static function sentenceCase(string $string): string
    {
        return ucfirst(strtolower(self::titleCase($string)));
    }

    /**
     * Convert a string to lower sentence case
     */
    public static function lowerSentenceCase(string $string): string
    {
        return strtolower(self::titleCase($string));
    }

    /**
     * Convert a string to UPPER SENTENCE CASE
     */
    public static function upperSentenceCase(string $string): string
    {
        return ucfirst(strtoupper(self::titleCase($string)));
    }

    /**
     * Convert a string to Title Case
     */
    public static function titleCase(string $string): string
    {
        $string = self::removeSpacingSymbols($string);
        return mb_convert_case($string, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Convert a string to UPPER-KEBAB-CASE
     */
    public static function upperKebabCase(string $string): string
    {
        return self::upperSpacerCase($string, '-');
    }

    /**
     * Convert a string to UPPER_SNAKE_CASE
     */
    public static function upperSnakeCase(string $string): string
    {
        return self::upperSpacerCase($string, '_');
    }

    /**
     * Convert a string to UPPER*SPACER*CASE
     */
    public static function upperSpacerCase(string $string, string $spacer): string
    {
        $string = self::titleCase($string);
        $string = implode($spacer, explode(' ', $string));
        return mb_strtoupper($string, 'UTF-8');
    }
}
