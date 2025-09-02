<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DataExchangeService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;

class DataExchangeController extends Controller
{
    protected $dataExchangeService;

    public function __construct(DataExchangeService $dataExchangeService)
    {
        $this->dataExchangeService = $dataExchangeService;
    }

    /**
     * Display the main dashboard
     */
    public function index()
    {
        return view('data-exchange.index');
    }

    /**
     * Test SOAP connection
     */
    public function testConnection()
    {
        try {
            $result = $this->dataExchangeService->testConnection();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show available SOAP functions
     */
    public function showAvailableFunctions()
    {
        try {
            $connectionResult = $this->dataExchangeService->testConnection();
            
            if ($connectionResult['status'] === 'success') {
                $functions = $connectionResult['functions'] ?? [];
                $types = $connectionResult['types'] ?? [];
                
                return view('data-exchange.available-methods', compact('functions', 'types'));
            } else {
                return view('data-exchange.available-methods', [
                    'error' => $connectionResult['message'] ?? 'Unable to retrieve SOAP methods'
                ]);
            }
        } catch (\Exception $e) {
            return view('data-exchange.available-methods', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Submit client data form
     */
    public function showClientForm()
    {
        $sampleData = $this->dataExchangeService->generateSampleClientData();
        return view('data-exchange.client-form', compact('sampleData'));
    }

    /**
     * Submit client data
     */
    public function submitClientData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string|max:50',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'date_of_birth' => 'required|date',
            'gender' => 'required|string|in:M,F,X,9',
            'postal_code' => 'nullable|string|max:10'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $result = $this->dataExchangeService->submitClientData($request->all());
            
            $response = redirect()->back()->with('success', 'Client data submitted successfully')
                ->with('result', $result);
            
            return $this->withDebugInfo($response);
                
        } catch (\Exception $e) {
            $response = redirect()->back()
                ->with('error', 'Failed to submit client data: ' . $e->getMessage())
                ->withInput();
            
            return $this->withDebugInfo($response);
        }
    }

    /**
     * Submit service data form
     */
    public function showServiceForm()
    {
        $sampleData = $this->dataExchangeService->generateSampleServiceData();
        return view('data-exchange.service-form', compact('sampleData'));
    }

    /**
     * Submit service data
     */
    public function submitServiceData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string|max:50',
            'service_type' => 'required|string|max:100',
            'service_start_date' => 'required|date',
            'service_end_date' => 'nullable|date|after_or_equal:service_start_date'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $result = $this->dataExchangeService->submitServiceData($request->all());
            
            $response = redirect()->back()->with('success', 'Service data submitted successfully')
                ->with('result', $result);
            
            return $this->withDebugInfo($response);
                
        } catch (\Exception $e) {
            $response = redirect()->back()
                ->with('error', 'Failed to submit service data: ' . $e->getMessage())
                ->withInput();
            
            return $this->withDebugInfo($response);
        }
    }

    /**
     * Bulk upload form
     */
    public function showBulkForm()
    {
        return view('data-exchange.bulk-form');
    }

    /**
     * Handle bulk upload
     */
    public function bulkUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt|max:2048'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        try {
            $file = $request->file('csv_file');
            $clientDataArray = $this->parseCsvFile($file);
            
            $results = $this->dataExchangeService->bulkSubmitClientData($clientDataArray);
            
            return view('data-exchange.bulk-results', compact('results'));
            
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to process bulk upload: ' . $e->getMessage());
        }
    }

    /**
     * Get submission status
     */
    public function getSubmissionStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'submission_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid submission ID'], 400);
        }

        try {
            $result = $this->dataExchangeService->getSubmissionStatus($request->submission_id);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show data retrieval form
     */
    public function showRetrieveForm()
    {
        $resources = $this->dataExchangeService->getAvailableResources();
        $reports = $this->dataExchangeService->getAvailableReports();
        
        return view('data-exchange.retrieve-form', compact('resources', 'reports'));
    }

    /**
     * Retrieve data
     */
    public function retrieveData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'resource_type' => 'required|string',
            'format' => 'required|in:json,xml,csv'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $filters = $this->buildFilters($request);
            $resourceType = $request->resource_type;
            
            // Get data based on resource type
            switch ($resourceType) {
                case 'clients':
                    $data = $this->dataExchangeService->getClientData($filters);
                    break;
                case 'cases':
                    $data = $this->dataExchangeService->getCaseData($filters);
                    break;
                case 'sessions':
                    $data = $this->dataExchangeService->getSessionData($filters);
                    break;
                case 'client_by_id':
                    if (empty($request->client_id)) {
                        throw new \Exception('Client ID is required for client lookup');
                    }
                    $data = $this->dataExchangeService->getClientById($request->client_id);
                    break;
                case 'case_by_id':
                    if (empty($request->case_id)) {
                        throw new \Exception('Case ID is required for case lookup');
                    }
                    $data = $this->dataExchangeService->getCaseById($request->case_id);
                    break;
                case 'session_by_id':
                    if (empty($request->session_id)) {
                        throw new \Exception('Session ID is required for session lookup');
                    }
                    $data = $this->dataExchangeService->getSessionById($request->session_id);
                    break;
                case 'sessions_for_case':
                    if (empty($request->case_id)) {
                        throw new \Exception('Case ID is required for sessions lookup');
                    }
                    $data = $this->dataExchangeService->getSessionsForCase($request->case_id);
                    break;
                default:
                    throw new \Exception("Unsupported resource type: {$resourceType}");
            }

            if ($request->action === 'download') {
                return $this->downloadData($data, $resourceType, $request->format);
            }

            $response = redirect()->back()
                ->with('success', 'Data retrieved successfully')
                ->with('data', $data)
                ->with('format', $request->format)
                ->withInput();
            
            return $this->withDebugInfo($response);

        } catch (\Exception $e) {
            $response = redirect()->back()
                ->with('error', 'Failed to retrieve data: ' . $e->getMessage())
                ->withInput();
            
            return $this->withDebugInfo($response);
        }
    }

    /**
     * Download data in specified format
     */
    public function downloadData($data, $resourceType, $format)
    {
        $filename = $resourceType . '_' . date('Y-m-d_H-i-s') . '.' . $format;
        $convertedData = $this->dataExchangeService->convertDataFormat($data, $format);
        
        $headers = [
            'Content-Type' => $this->getContentType($format),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response($convertedData, 200, $headers);
    }

    /**
     * Get content type for format
     */
    protected function getContentType($format)
    {
        switch (strtolower($format)) {
            case 'json':
                return 'application/json';
            case 'xml':
                return 'application/xml';
            case 'csv':
                return 'text/csv';
            default:
                return 'text/plain';
        }
    }

    /**
     * Generate report
     */
    public function generateReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|string',
            'format' => 'required|in:json,xml,csv'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid parameters'], 400);
        }

        try {
            $parameters = $this->buildReportParameters($request);
            $data = $this->dataExchangeService->getReportingData($request->report_type, $parameters);
            
            if ($request->action === 'download') {
                return $this->downloadData($data, $request->report_type . '_report', $request->format);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'format' => $request->format
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get resource schema
     */
    public function getResourceSchema(Request $request)
    {
        $resourceType = $request->get('resource_type');
        
        if (!$resourceType) {
            return response()->json(['error' => 'Resource type is required'], 400);
        }

        try {
            $schema = $this->dataExchangeService->getResourceSchema($resourceType);
            return response()->json($schema);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build filters from request
     */
    protected function buildFilters(Request $request)
    {
        $filters = [];
        
        // Standard filters
        $filterFields = [
            'client_id', 'first_name', 'last_name', 'gender', 'postal_code',
            'case_id', 'case_status', 'case_type',
            'session_id', 'session_type', 'session_status',
            'service_type', 'service_start_date', 'service_end_date',
            'date_from', 'date_to', 'status'
        ];
        
        foreach ($filterFields as $field) {
            if ($request->has($field) && !empty($request->get($field))) {
                $filters[$field] = $request->get($field);
            }
        }
        
        return $filters;
    }


    /**
     * Parse CSV file for bulk upload
     */
    /**
     * Add debug information to redirect response if enabled
     */
    protected function withDebugInfo($response)
    {
        $config = Config::get('soap.dss.debug');
        
        if ($config['web_display_enabled']) {
            if ($config['show_requests']) {
                $response = $response->with('request', $this->dataExchangeService->getSanitizedLastRequest());
            }
            if ($config['show_responses']) {
                $response = $response->with('response', $this->dataExchangeService->getSanitizedLastResponse());
            }
        }
        
        return $response;
    }

    /**
     * Parse CSV file for bulk upload
     */
    protected function parseCsvFile($file)
    {
        $clientDataArray = [];
        $handle = fopen($file->getPathname(), 'r');
        
        // Skip header row
        fgetcsv($handle);
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $clientDataArray[] = [
                'client_id' => $data[0] ?? null,
                'first_name' => $data[1] ?? null,
                'last_name' => $data[2] ?? null,
                'date_of_birth' => $data[3] ?? null,
                'gender' => $data[4] ?? null,
                'indigenous_status' => $data[5] ?? null,
                'country_of_birth' => $data[6] ?? null,
                'postal_code' => $data[7] ?? null,
                'primary_language' => $data[8] ?? null,
                'interpreter_required' => $data[9] === 'true' ? true : false,
                'disability_flag' => $data[10] === 'true' ? true : false,
                'client_type' => $data[11] ?? null,
            ];
        }
        
        fclose($handle);
        return $clientDataArray;
    }
}