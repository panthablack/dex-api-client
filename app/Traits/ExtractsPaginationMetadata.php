<?php

namespace App\Traits;

trait ExtractsPaginationMetadata
{
    /**
     * Extract TotalCount from SOAP API response metadata
     * Checks multiple possible locations for the TotalCount value
     *
     * @param mixed $response The SOAP API response (object or array)
     * @return int The total count, or 0 if not found
     */
    protected function extractTotalCountFromResponse($response): int
    {
        // Handle different response structures from SOAP APIs
        if (is_object($response)) {
            $response = json_decode(json_encode($response), true);
        }

        if (!is_array($response)) {
            return 0;
        }

        // Check for TotalCount in various possible locations
        $possiblePaths = [
            'TotalCount',
            'totalCount',
            'total_count',
            'pagination.total_items',
            'pagination.TotalCount',
            'Pagination.TotalCount',
            'SearchResult.TotalCount',
            'Result.TotalCount'
        ];

        foreach ($possiblePaths as $path) {
            $value = data_get($response, $path);
            if (is_numeric($value) && $value >= 0) {
                return (int) $value;
            }
        }

        return 0;
    }
}
