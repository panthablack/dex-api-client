<?php

namespace App\Services;

use App\Helpers\ExportHelper;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ExportService
{
    /**
     * Export a data collection to the specified format
     *
     * @param mixed $data Collection or query builder result
     * @param array $headerMapping Field name => Human-readable header mapping
     * @param string $format Format type (csv, json, xlsx)
     * @param string $filename Base filename without extension
     * @return Response
     */
    public function export($data, array $headerMapping, string $format, string $filename)
    {
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['format' => $format],
            ['format' => 'required|in:csv,json,xlsx']
        );

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Invalid export format: ' . $format);
        }

        try {
            return match ($format) {
                'csv' => $this->exportToCsv($data, $headerMapping, $filename),
                'json' => $this->exportToJson($data, $headerMapping, $filename),
                'xlsx' => $this->exportToExcel($data, $headerMapping, $filename),
            };
        } catch (\Exception $e) {
            Log::error('Failed to export data: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Export data to CSV
     */
    protected function exportToCsv($data, array $headerMapping, string $filename): Response
    {
        $csvContent = ExportHelper::createCsvFromData($data, $headerMapping);

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\""
        ]);
    }

    /**
     * Export data to JSON
     */
    protected function exportToJson($data, array $headerMapping, string $filename)
    {
        $transformedData = ExportHelper::transformForJsonExport($data, $headerMapping);

        return response()->json($transformedData->isNotEmpty() ? $transformedData : [], 200, [
            'Content-Disposition' => "attachment; filename=\"{$filename}.json\""
        ]);
    }

    /**
     * Export data to Excel (simplified - export as CSV with .xlsx extension)
     */
    protected function exportToExcel($data, array $headerMapping, string $filename): Response
    {
        return $this->exportToCsv($data, $headerMapping, $filename . '.xlsx');
    }
}
