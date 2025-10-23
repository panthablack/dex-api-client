<?php

namespace App\Helpers;

use App\Enums\VerificationStatus;
use Carbon\Carbon;

class ExportHelper
{
    /**
     * Format a single value for CSV export with proper type transformation
     */
    public static function formatValueForCsv($value): string
    {
        $value = match (true) {
            // Boolean values
            is_bool($value) => $value ? 'Yes' : 'No',

            // VerificationStatus enum
            $value instanceof VerificationStatus => $value->value,

            // Dates
            $value instanceof Carbon => $value->format('Y-m-d H:i:s'),

            // All arrays (simple or deep) are preserved as JSON
            is_array($value) => json_encode($value),

            // Null values
            is_null($value) => '',

            // Default: convert to string
            default => (string) $value
        };

        return $value;
    }

    /**
     * Format a single value for JSON export
     */
    public static function formatValueForJson($value)
    {
        return match (true) {
            // VerificationStatus enum
            $value instanceof VerificationStatus => $value->value,

            // Keep dates as ISO strings for JSON
            $value instanceof Carbon => $value->toISOString(),

            // Keep other values as-is for JSON (booleans, arrays, etc.)
            default => $value
        };
    }

    /**
     * Format a row for CSV export using field order and header mapping
     */
    public static function formatRowForCsv($row, array $fieldOrder): array
    {
        $formattedRow = [];

        foreach ($fieldOrder as $field) {
            $value = $row->$field ?? '';
            $formattedRow[] = self::formatValueForCsv($value);
        }

        return $formattedRow;
    }

    /**
     * Transform a model instance for JSON export
     */
    public static function transformForJson($row, array $fieldOrder)
    {
        $filteredRow = [];
        foreach ($fieldOrder as $field) {
            $value = $row->$field;
            $filteredRow[$field] = self::formatValueForJson($value);
        }
        return $filteredRow;
    }

    /**
     * Create CSV writer from data collection using header mapping
     */
    public static function createCsvFromData($data, array $headerMapping): string
    {
        $csv = \League\Csv\Writer::createFromString('');
        $fieldOrder = array_keys($headerMapping);
        $headers = array_values($headerMapping);

        // Add headers
        $csv->insertOne($headers);

        // Add data rows
        if ($data->isNotEmpty()) {
            foreach ($data as $row) {
                $formattedRow = self::formatRowForCsv($row, $fieldOrder);
                $csv->insertOne($formattedRow);
            }
        }

        return $csv->toString();
    }

    /**
     * Transform data collection for JSON export
     */
    public static function transformForJsonExport($data, array $headerMapping)
    {
        $fieldOrder = array_keys($headerMapping);

        return $data->map(function ($row) use ($fieldOrder) {
            return self::transformForJson($row, $fieldOrder);
        });
    }
}
