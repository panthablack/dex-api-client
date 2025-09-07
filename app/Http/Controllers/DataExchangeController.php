<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DataExchangeService;
use App\Helpers\ReferenceData;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

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

        // Get reference data for dropdowns
        $countries = ReferenceData::countries();
        $languages = ReferenceData::languages();
        $atsiOptions = ReferenceData::aboriginalOrTorresStraitIslanderOrigin();

        return view('data-exchange.client-form', compact('sampleData', 'countries', 'languages', 'atsiOptions'));
    }

    /**
     * Show case data form
     */
    public function showCaseForm()
    {
        $sampleData = $this->dataExchangeService->generateSampleCaseData();

        // Try to fetch outlet activities
        $outletActivities = [];
        try {
            $outletActivitiesResult = $this->dataExchangeService->getOutletActivities();
            if (isset($outletActivitiesResult->OutletActivities->OutletActivity)) {
                $outletActivities = $outletActivitiesResult->OutletActivities->OutletActivity;
                // Ensure it's an array
                if (!is_array($outletActivities)) {
                    $outletActivities = [$outletActivities];
                }
            }
        } catch (\Exception $e) {
            // Log error but continue with empty array - form has fallback options
            Log::warning('Failed to fetch outlet activities: ' . $e->getMessage());
        }

        // Get referral source options from ReferenceData helper
        $referralSources = ReferenceData::referralSource();

        // Get attendance profile options from ReferenceData helper
        $attendanceProfiles = ReferenceData::attendanceProfile();

        // Get exit reason options from ReferenceData helper
        $exitReasons = ReferenceData::exitReason();

        return view('data-exchange.case-form', compact('sampleData', 'outletActivities', 'referralSources', 'attendanceProfiles', 'exitReasons'));
    }

    /**
     * Show session data form
     */
    public function showSessionForm()
    {
        $sampleData = $this->dataExchangeService->generateSampleSessionData();

        // TODO: Get actual ServiceTypeId values from GetOrganisationActivities API
        // For now, using example ServiceTypeId from DSS spec
        $serviceTypes = [
            (object) ['ServiceTypeId' => 5, 'ServiceTypeName' => 'Counselling']
        ];

        return view('data-exchange.session-form', compact('sampleData', 'serviceTypes'));
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
            'suburb' => 'required|string|max:100',
            'state' => 'required|string|max:10',
            'postal_code' => 'required|string|max:10',
            'consent_to_provide_details' => 'required|accepted',
            'consent_to_be_contacted' => 'required|accepted'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $result = $this->dataExchangeService->submitClientData($request->all());

            // Check if the response contains a failed transaction status
            $transactionStatus = $this->extractTransactionStatus($result);

            if ($transactionStatus && $transactionStatus['statusCode'] === 'Failed') {
                $errorMessage = $transactionStatus['message'] ?? 'Client data submission failed';

                $response = redirect()->back()
                    ->with('error', $errorMessage)
                    ->with('result', $result)
                    ->withInput();
            } else {
                $response = redirect()->back()->with('success', 'Client data submitted successfully')
                    ->with('result', $result);
            }

            return $this->withDebugInfo($response);
        } catch (\Exception $e) {
            $response = redirect()->back()
                ->with('error', 'Failed to submit client data: ' . $e->getMessage())
                ->withInput();

            return $this->withDebugInfo($response);
        }
    }

    /**
     * Submit case data
     */
    public function submitCaseData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Required DSS fields
            'case_id' => 'required|string|max:50',
            'outlet_activity_id' => 'required|integer',
            'client_id' => 'required|string|max:50',
            'referral_source_code' => 'required|string|max:50',
            'reasons_for_assistance' => 'required|array|min:1',
            'reasons_for_assistance.*' => 'string|in:PHYSICAL,EMOTIONAL,FINANCIAL,HOUSING,LEGAL',

            // Optional DSS fields
            'total_unidentified_clients' => 'nullable|integer|min:0|max:100',
            'client_attendance_profile_code' => 'nullable|string|in:PSGROUP,INDIVIDUAL,FAMILY',
            'end_date' => 'nullable|date|before:today|after:' . now()->subDays(60)->format('Y-m-d'),
            'exit_reason_code' => 'nullable|string|in:NOLONGERASSIST,CANNOTASSIST,HIGHERASSISTANCE,MOVED,QUITSERVICE,DECEASED,NEEDSMET,NOLONGERELIGIBLE,OTHER',
            'ag_business_type_code' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $result = $this->dataExchangeService->submitCaseData($request->all());

            // Check transaction status in the response
            $transactionStatus = $this->extractTransactionStatus($result);

            if ($transactionStatus && $transactionStatus['statusCode'] === 'Failed') {
                $errorMessage = $transactionStatus['message'] ?? 'Case submission failed';
                $response = redirect()->back()
                    ->with('error', 'Case submission failed: ' . $errorMessage)
                    ->with('result', $result)
                    ->withInput();
            } else {
                $response = redirect()->back()->with('success', 'Case data submitted successfully')
                    ->with('result', $result);
            }

            return $this->withDebugInfo($response);
        } catch (\Exception $e) {
            $response = redirect()->back()
                ->with('error', 'Failed to submit case data: ' . $e->getMessage())
                ->withInput();

            return $this->withDebugInfo($response);
        }
    }

    /**
     * Submit session data
     */
    public function submitSessionData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|max:50',
            'case_id' => 'required|string|max:50',
            'service_type_id' => 'required|integer',
            'session_date' => 'required|date|after_or_equal:' . now()->subDays(60)->format('Y-m-d') . '|before_or_equal:today',
            'duration_minutes' => 'required|integer|min:1',
            'location' => 'nullable|string|max:200',
            'session_status' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $result = $this->dataExchangeService->submitSessionData($request->all());

            // Check transaction status in the response
            $transactionStatus = $this->extractTransactionStatus($result);

            if ($transactionStatus && $transactionStatus['statusCode'] === 'Failed') {
                $errorMessage = $transactionStatus['message'] ?? 'Session submission failed';
                $response = redirect()->back()
                    ->with('error', 'Session submission failed: ' . $errorMessage)
                    ->with('result', $result)
                    ->withInput();
            } else {
                $response = redirect()->back()->with('success', 'Session data submitted successfully')
                    ->with('result', $result);
            }

            return $this->withDebugInfo($response);
        } catch (\Exception $e) {
            $response = redirect()->back()
                ->with('error', 'Failed to submit session data: ' . $e->getMessage())
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
     * Show bulk clients form
     */
    public function showBulkClientsForm()
    {
        return view('data-exchange.bulk-clients');
    }

    /**
     * Handle bulk clients upload
     */
    public function bulkUploadClients(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt|max:2048'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        try {
            $file = $request->file('csv_file');
            $clientDataArray = $this->parseCsvFile($file, 'clients');
            $results = $this->dataExchangeService->bulkSubmitClientData($clientDataArray);
            return view('data-exchange.bulk-results', compact('results'));
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to process bulk client upload: ' . $e->getMessage());
        }
    }

    /**
     * Show bulk cases form
     */
    public function showBulkCasesForm()
    {
        return view('data-exchange.bulk-cases');
    }

    /**
     * Handle bulk cases upload
     */
    public function bulkUploadCases(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt|max:2048'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        try {
            $file = $request->file('csv_file');
            $caseDataArray = $this->parseCsvFile($file, 'cases');
            $results = $this->dataExchangeService->bulkSubmitCaseData($caseDataArray);
            return view('data-exchange.bulk-results', compact('results'));
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to process bulk case upload: ' . $e->getMessage());
        }
    }

    /**
     * Show bulk sessions form
     */
    public function showBulkSessionsForm()
    {
        return view('data-exchange.bulk-sessions');
    }

    /**
     * Handle bulk sessions upload
     */
    public function bulkUploadSessions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt|max:2048'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        try {
            $file = $request->file('csv_file');
            $sessionDataArray = $this->parseCsvFile($file, 'sessions');
            $results = $this->dataExchangeService->bulkSubmitSessionData($sessionDataArray);
            return view('data-exchange.bulk-results', compact('results'));
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to process bulk session upload: ' . $e->getMessage());
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
                case 'full_cases':
                    $data = $this->dataExchangeService->fetchFullCaseData($filters);
                    break;
                case 'full_sessions':
                    $data = $this->dataExchangeService->fetchFullSessionData($filters);
                    break;
                case 'sessions':
                    // Debug logging
                    Log::info('Session request debug', [
                        'request_case_id' => $request->case_id,
                        'request_has_case_id' => $request->has('case_id'),
                        'request_case_id_empty' => empty($request->case_id),
                        'filters' => $filters,
                        'filters_has_case_id' => isset($filters['case_id']),
                        'all_request_data' => $request->all()
                    ]);

                    if (empty($request->case_id)) {
                        throw new \Exception('Case ID is required for session data retrieval. Sessions are linked to specific cases in the DSS system. Received case_id: "' . ($request->case_id ?? 'null') . '" (type: ' . gettype($request->case_id) . ')');
                    }

                    // Ensure case_id is in filters even if buildFilters missed it
                    if (!isset($filters['case_id']) && $request->case_id) {
                        $filters['case_id'] = $request->case_id;
                        Log::info('Added case_id to filters manually: ' . $request->case_id);
                    }

                    $data = $this->dataExchangeService->getSessionData($filters);
                    break;
                case 'client_by_id':
                    if (empty($request->client_id)) {
                        throw new \Exception('Client ID is required for client lookup');
                    }
                    $data = $this->dataExchangeService->getClientById($request->client_id);
                    break;
                case 'case_by_id':
                    // Debug logging for case_by_id
                    Log::info('Case by ID request debug', [
                        'request_case_id' => $request->case_id,
                        'request_has_case_id' => $request->has('case_id'),
                        'request_case_id_empty' => empty($request->case_id),
                        'request_case_id_value' => $request->get('case_id'),
                        'all_request_data' => $request->all()
                    ]);

                    if (empty($request->case_id)) {
                        throw new \Exception('Case ID is required for case lookup. Received: "' . ($request->case_id ?? 'null') . '" (type: ' . gettype($request->case_id) . ')');
                    }
                    $data = $this->dataExchangeService->getCaseById($request->case_id);
                    break;
                case 'session_by_id':
                    if (empty($request->session_id)) {
                        throw new \Exception('Session ID is required for session lookup');
                    }
                    if (empty($request->case_id)) {
                        throw new \Exception('Case ID is required for session lookup');
                    }
                    $data = $this->dataExchangeService->getSessionById($request->session_id, $request->case_id);
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
                ->with('resource_type', $resourceType)
                ->withInput();

            // Store resource ID in session for individual resource lookups
            if (in_array($resourceType, ['client_by_id', 'case_by_id', 'session_by_id'])) {
                $resourceId = '';
                if ($resourceType === 'client_by_id') {
                    $resourceId = $request->client_id;
                } elseif ($resourceType === 'case_by_id') {
                    $resourceId = $request->case_id;
                } elseif ($resourceType === 'session_by_id') {
                    $resourceId = $request->session_id;
                }
                $response->with('resource_id', $resourceId);
            }

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
     * Show resource schema
     */
    public function showResourceSchema(Request $request)
    {
        $resourceType = $request->get('resource_type');

        if (!$resourceType) {
            return view('data-exchange.resource-schema', [
                'error' => 'Resource type is required. Please select a resource type first.',
                'resource_type' => null
            ]);
        }

        try {
            $schema = $this->dataExchangeService->getResourceSchema($resourceType);
            return view('data-exchange.resource-schema', [
                'schema' => $schema,
                'resource_type' => $resourceType
            ]);
        } catch (\Exception $e) {
            return view('data-exchange.resource-schema', [
                'error' => $e->getMessage(),
                'resource_type' => $resourceType
            ]);
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
            'client_id',
            'first_name',
            'last_name',
            'gender',
            'postal_code',
            'state',
            'case_id',
            'case_id_filter',
            'case_status',
            'case_type',
            'session_id',
            'session_status',
            'service_type',
            'service_type_id',
            'service_start_date',
            'service_end_date',
            'date_from',
            'date_to',
            'status',
            'referral_source_code',
            'outlet_activity_id',
            'client_type',
            'indigenous_status',
            'country_of_birth',
            'primary_language'
        ];

        foreach ($filterFields as $field) {
            if ($request->has($field) && !empty($request->get($field))) {
                $filters[$field] = $request->get($field);
            }
        }

        // Handle date_range parameter for convenience
        if ($request->has('date_range') && !empty($request->get('date_range'))) {
            $dateRange = intval($request->get('date_range'));
            if ($dateRange > 0) {
                $filters['date_from'] = now()->subDays($dateRange)->format('Y-m-d');
                $filters['date_to'] = now()->format('Y-m-d');
            }
        }

        // Normalize case_id_filter to case_id for consistency
        if (!empty($filters['case_id_filter']) && empty($filters['case_id'])) {
            $filters['case_id'] = $filters['case_id_filter'];
        }

        return $filters;
    }

    /**
     * Generate report
     */
    public function generateReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|string',
            'format' => 'required|in:json,xml,csv,pdf'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $filters = $this->buildFilters($request);
            $reportType = $request->report_type;

            $data = $this->dataExchangeService->generateReport($reportType, $filters);

            if ($request->action === 'download') {
                return $this->downloadData($data, $reportType, $request->format);
            }

            $response = redirect()->back()
                ->with('success', 'Report generated successfully')
                ->with('data', $data)
                ->with('format', $request->format)
                ->withInput();

            return $this->withDebugInfo($response);
        } catch (\Exception $e) {
            $response = redirect()->back()
                ->with('error', 'Failed to generate report: ' . $e->getMessage())
                ->withInput();

            return $this->withDebugInfo($response);
        }
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

        if ($config && $config['web_display_enabled']) {
            try {
                if ($config['show_requests']) {
                    $request = $this->dataExchangeService->getSanitizedLastRequest();
                    $response = $response->with('request', $request ?: 'No request data available');
                }
                if ($config['show_responses']) {
                    $responseData = $this->dataExchangeService->getSanitizedLastResponse();
                    $response = $response->with('response', $responseData ?: 'No response data available');
                }
            } catch (\Exception $e) {
                // If debug info fails, don't break the main response
                $response = $response->with('debug_error', 'Debug info unavailable: ' . $e->getMessage());
            }
        }

        return $response;
    }

    /**
     * Generate fake data for bulk upload
     */
    public function generateFakeData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:clients,cases,sessions',
            'count' => 'required|integer|min:1|max:1000',
            'format' => 'required|in:csv,json'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        try {
            $type = $request->type;
            $count = $request->count;
            $format = $request->format;

            if ($format === 'csv') {
                $content = $this->dataExchangeService->generateFakeCSV($type, $count);
                $filename = "fake_{$type}_" . date('Y-m-d_H-i-s') . '.csv';

                return response($content, 200, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => "attachment; filename=\"{$filename}\""
                ]);
            } else {
                $data = [];
                switch ($type) {
                    case 'clients':
                        $data = $this->dataExchangeService->generateFakeClientData($count);
                        break;
                    case 'cases':
                        $data = $this->dataExchangeService->generateFakeCaseData($count);
                        break;
                    case 'sessions':
                        $data = $this->dataExchangeService->generateFakeSessionData($count);
                        break;
                }

                return response()->json($data);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate fake data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate fake dataset with related records
     */
    public function generateFakeDataset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_count' => 'integer|min:1|max:100',
            'cases_per_client' => 'integer|min:1|max:10',
            'sessions_per_case' => 'integer|min:1|max:20',
            'format' => 'required|in:json,download_csv'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        try {
            $clientCount = $request->get('client_count', 5);
            $casesPerClient = $request->get('cases_per_client', 2);
            $sessionsPerCase = $request->get('sessions_per_case', 3);
            $format = $request->format;

            $dataset = $this->dataExchangeService->generateFakeDataSet(
                $clientCount,
                $casesPerClient,
                $sessionsPerCase
            );

            if ($format === 'download_csv') {
                // Generate separate CSV files for each type
                $timestamp = date('Y-m-d_H-i-s');

                $response = response()->json([
                    'message' => 'Dataset generated successfully',
                    'summary' => [
                        'clients' => count($dataset['clients']),
                        'cases' => count($dataset['cases']),
                        'sessions' => count($dataset['sessions'])
                    ],
                    'download_links' => [
                        'clients' => route('data-exchange.download-fake-csv', ['type' => 'clients', 'timestamp' => $timestamp]),
                        'cases' => route('data-exchange.download-fake-csv', ['type' => 'cases', 'timestamp' => $timestamp]),
                        'sessions' => route('data-exchange.download-fake-csv', ['type' => 'sessions', 'timestamp' => $timestamp])
                    ]
                ]);

                // Store dataset in session for download
                session(['fake_dataset_' . $timestamp => $dataset]);

                return $response;
            } else {
                return response()->json($dataset);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate fake dataset: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download fake CSV from generated dataset
     */
    public function downloadFakeCSV($type, $timestamp)
    {
        $dataset = session('fake_dataset_' . $timestamp);

        if (!$dataset || !isset($dataset[$type])) {
            abort(404, 'Dataset not found or expired');
        }

        $content = $this->dataExchangeService->arrayToCsv($dataset[$type]);
        $filename = "fake_{$type}_{$timestamp}.csv";

        return response($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\""
        ]);
    }

    /**
     * Show reference data explorer page
     */
    public function showReferenceData()
    {
        return view('data-exchange.reference-data');
    }

    /**
     * Get reference data via AJAX
     */
    public function getReferenceData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference_type' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Reference type is required'
            ], 400);
        }

        $referenceType = $request->reference_type;

        try {
            $referenceData = $this->dataExchangeService->getReferenceData($referenceType);

            return response()->json([
                'success' => true,
                'reference_type' => $referenceType,
                'data' => $referenceData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'reference_type' => $referenceType
            ], 500);
        }
    }

    /**
     * Test reference data retrieval (legacy method)
     */
    public function testReferenceData(Request $request)
    {
        $referenceType = $request->get('type', 'CountryCode');

        try {
            $referenceData = $this->dataExchangeService->getReferenceData($referenceType);

            return response()->json([
                'success' => true,
                'reference_type' => $referenceType,
                'data' => $referenceData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'reference_type' => $referenceType
            ], 500);
        }
    }

    /**
     * Parse CSV file for bulk upload
     */
    protected function parseCsvFile($file, $type = 'clients')
    {
        $dataArray = [];
        $handle = fopen($file->getPathname(), 'r');

        // Get header row to understand column structure
        $headers = fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== FALSE) {
            // Create associative array with headers as keys
            $rowData = array_combine($headers, $data);

            // Clean up the data based on type
            if ($type === 'clients') {
                $dataArray[] = $this->cleanClientData($rowData);
            } elseif ($type === 'cases') {
                $dataArray[] = $this->cleanCaseData($rowData);
            } elseif ($type === 'sessions') {
                $dataArray[] = $this->cleanSessionData($rowData);
            }
        }

        fclose($handle);
        return $dataArray;
    }

    protected function cleanClientData($rowData)
    {
        return [
            'client_id' => $rowData['client_id'] ?? null,
            'first_name' => $rowData['first_name'] ?? null,
            'last_name' => $rowData['last_name'] ?? null,
            'date_of_birth' => $rowData['date_of_birth'] ?? null,
            'is_birth_date_estimate' => in_array(strtolower($rowData['is_birth_date_estimate'] ?? ''), ['true', '1', 'yes']),
            'gender' => $rowData['gender'] ?? null,
            'suburb' => $rowData['suburb'] ?? null,
            'state' => $rowData['state'] ?? null,
            'postal_code' => $rowData['postal_code'] ?? null,
            'country_of_birth' => $rowData['country_of_birth'] ?? null,
            'primary_language' => $rowData['primary_language'] ?? null,
            'indigenous_status' => $rowData['indigenous_status'] ?? '9',
            'interpreter_required' => in_array(strtolower($rowData['interpreter_required'] ?? ''), ['true', '1', 'yes']),
            'disability_flag' => in_array(strtolower($rowData['disability_flag'] ?? ''), ['true', '1', 'yes']),
            'is_using_pseudonym' => in_array(strtolower($rowData['is_using_pseudonym'] ?? ''), ['true', '1', 'yes']),
            'consent_to_provide_details' => in_array(strtolower($rowData['consent_to_provide_details'] ?? ''), ['true', '1', 'yes']),
            'consent_to_be_contacted' => in_array(strtolower($rowData['consent_to_be_contacted'] ?? ''), ['true', '1', 'yes']),
            'client_type' => $rowData['client_type'] ?? 'Individual',
        ];
    }

    protected function cleanCaseData($rowData)
    {
        return [
            'case_id' => $rowData['case_id'] ?? null,
            'client_id' => $rowData['client_id'] ?? null,
            'outlet_activity_id' => intval($rowData['outlet_activity_id'] ?? 61932),
            'referral_source_code' => $rowData['referral_source_code'] ?? 'COMMUNITY',
            'reasons_for_assistance' => isset($rowData['reasons_for_assistance']) ?
                (is_string($rowData['reasons_for_assistance']) ? explode(',', $rowData['reasons_for_assistance']) : $rowData['reasons_for_assistance']) :
                ['PHYSICAL'],
            'total_unidentified_clients' => !empty($rowData['total_unidentified_clients']) ? intval($rowData['total_unidentified_clients']) : null,
            'client_attendance_profile_code' => $rowData['client_attendance_profile_code'] ?? null,
            'end_date' => $rowData['end_date'] ?? null,
            'exit_reason_code' => $rowData['exit_reason_code'] ?? null,
            'ag_business_type_code' => $rowData['ag_business_type_code'] ?? null,
        ];
    }

    protected function cleanSessionData($rowData)
    {
        return [
            'session_id' => $rowData['session_id'] ?? null,
            'case_id' => $rowData['case_id'] ?? null,
            'service_type_id' => $rowData['service_type_id'] ?? null,
            'session_date' => $rowData['session_date'] ?? null,
            'duration_minutes' => intval($rowData['duration_minutes'] ?? 0),
            'location' => $rowData['location'] ?? null,
            'session_status' => $rowData['session_status'] ?? null,
            'attendees' => $rowData['attendees'] ?? null,
            'outcome' => $rowData['outcome'] ?? null,
            'notes' => $rowData['notes'] ?? null,
        ];
    }

    /**
     * Extract transaction status from DSS API response
     */
    protected function extractTransactionStatus($result)
    {
        // Convert objects to arrays for consistent handling
        if (is_object($result)) {
            $result = json_decode(json_encode($result), true);
        }

        if (!is_array($result)) {
            return null;
        }

        // Check for TransactionStatus in the response
        if (isset($result['TransactionStatus'])) {
            $transactionStatus = $result['TransactionStatus'];

            return [
                'statusCode' => $transactionStatus['TransactionStatusCode'] ?? null,
                'message' => $transactionStatus['Messages']['Message']['MessageDescription'] ?? null
            ];
        }

        return null;
    }

    /**
     * Get client data by ID for update form
     */
    public function getClient($id)
    {
        try {
            $data = $this->dataExchangeService->getClientById($id);
            return response()->json([
                'success' => true,
                'resource' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update client data
     */
    public function updateClient(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:M,F,X,9'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->dataExchangeService->updateClient($id, $request->all());
            return response()->json([
                'success' => true,
                'message' => 'Client updated successfully',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update client: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete client
     */
    public function deleteClient($id)
    {
        try {
            $result = $this->dataExchangeService->deleteClient($id);
            return response()->json([
                'success' => true,
                'message' => 'Client deleted successfully',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete client: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get case data by ID for update form
     */
    public function getCase($id)
    {
        try {
            $data = $this->dataExchangeService->getCaseById($id);
            return response()->json([
                'success' => true,
                'resource' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update case data
     */
    public function updateCase(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'referral_source_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->dataExchangeService->updateCase($id, $request->all());
            return response()->json([
                'success' => true,
                'message' => 'Case updated successfully',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update case: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete case
     */
    public function deleteCase($id)
    {
        try {
            $result = $this->dataExchangeService->deleteCase($id);
            return response()->json([
                'success' => true,
                'message' => 'Case deleted successfully',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete case: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get session data by ID for update form
     */
    public function getSession(Request $request, $id)
    {
        try {
            $caseId = $request->get('case_id');
            
            // If case_id is provided, use it directly
            if ($caseId) {
                $data = $this->dataExchangeService->getSessionById($id, $caseId);
                return response()->json([
                    'success' => true,
                    'resource' => $data
                ]);
            }
            
            // Fallback: try to find the case_id by searching through all sessions
            $allSessionsData = $this->dataExchangeService->getSessionData([]);
            $foundCaseId = null;
            
            if (isset($allSessionsData['sessions'])) {
                foreach ($allSessionsData['sessions'] as $session) {
                    if (isset($session['SessionDetails']['SessionId']) && 
                        $session['SessionDetails']['SessionId'] === $id) {
                        $foundCaseId = $session['CaseId'] ?? null;
                        break;
                    }
                }
            }
            
            if ($foundCaseId) {
                $data = $this->dataExchangeService->getSessionById($id, $foundCaseId);
                return response()->json([
                    'success' => true,
                    'resource' => $data
                ]);
            }
            
            // If all else fails, return a helpful error message
            return response()->json([
                'success' => false,
                'message' => 'Session retrieval requires a Case ID. Unable to find Case ID for session: ' . $id
            ], 400);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Session retrieval failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update session data
     */
    public function updateSession(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'session_status' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:1',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->dataExchangeService->updateSession($id, $request->all());
            return response()->json([
                'success' => true,
                'message' => 'Session updated successfully',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update session: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete session
     */
    public function deleteSession($id)
    {
        try {
            $result = $this->dataExchangeService->deleteSession($id);
            return response()->json([
                'success' => true,
                'message' => 'Session deleted successfully',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete session: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show clients index page
     */
    public function clientsIndex(Request $request)
    {
        try {
            $filters = $this->buildFilters($request);
            $loading = false;
            $clients = [];
            $debugInfo = [];

            // Load data with default filters
            try {
                $rawData = $this->dataExchangeService->getClientData($filters);
                
                $debugInfo['raw_data_type'] = gettype($rawData);
                $debugInfo['filters_applied'] = $filters;
                
                // Convert to array if it's an object
                if (is_object($rawData)) {
                    $rawData = json_decode(json_encode($rawData), true);
                }
                
                $debugInfo['converted_data_keys'] = is_array($rawData) ? array_keys($rawData) : 'not_array';
                
                // Handle different response structures - be more flexible
                $clients = $this->extractClientsFromResponse($rawData);
                
                $debugInfo['final_clients_count'] = count($clients);
                $debugInfo['sample_client'] = !empty($clients) ? array_slice($clients, 0, 1) : null;
                
            } catch (\Exception $e) {
                $debugInfo = [
                    'error' => $e->getMessage(),
                    'filters_applied' => $filters,
                    'stack_trace' => $e->getTraceAsString()
                ];
                Log::error('Failed to load clients: ' . $e->getMessage());
                
                // Add sample data for testing when API fails (only in debug mode)
                if (config('app.debug', false)) {
                    $clients = $this->getSampleClients();
                    $debugInfo['using_sample_data'] = true;
                }
            }

            // Enable debug info only when needed (set to false for production)
            $debugInfo['view_debug'] = config('app.debug', false);

            return view('data-exchange.clients.index', compact('clients', 'loading', 'debugInfo'));
        } catch (\Exception $e) {
            return view('data-exchange.clients.index', [
                'clients' => [],
                'loading' => false,
                'debugInfo' => ['controller_error' => $e->getMessage()]
            ])->with('error', 'Failed to load clients: ' . $e->getMessage());
        }
    }

    /**
     * Extract clients from various response structures
     */
    private function extractClientsFromResponse($data)
    {
        if (empty($data)) {
            return [];
        }

        // If it's already an array of clients (has numeric keys)
        if (is_array($data) && isset($data[0])) {
            return $data;
        }

        // Check various nested structures
        $possiblePaths = [
            'Clients.Client',
            'Client',
            'clients',
            'data'
        ];

        foreach ($possiblePaths as $path) {
            $result = data_get($data, $path);
            if (!empty($result)) {
                // If single item, wrap in array
                if (!is_array($result) || (is_array($result) && !isset($result[0]))) {
                    return [$result];
                }
                return $result;
            }
        }

        // If we have some data but couldn't extract it through normal paths
        if (is_array($data)) {
            // Check if this looks like a single client record
            if (isset($data['client_id']) || isset($data['ClientId']) || 
                isset($data['first_name']) || isset($data['FirstName'])) {
                return [$data];
            }
        }

        return [];
    }

    /**
     * Show cases index page
     */
    public function casesIndex(Request $request)
    {
        try {
            $filters = $this->buildFilters($request);
            $loading = false;
            $cases = [];
            $debugInfo = [];
            $outletActivities = [];

            // Get outlet activities for filter dropdown
            try {
                $outletActivitiesResult = $this->dataExchangeService->getOutletActivities();
                if (isset($outletActivitiesResult->OutletActivities->OutletActivity)) {
                    $outletActivities = $outletActivitiesResult->OutletActivities->OutletActivity;
                    if (!is_array($outletActivities)) {
                        $outletActivities = [$outletActivities];
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to load outlet activities: ' . $e->getMessage());
            }

            // Load data with default filters
            try {
                $rawData = $this->dataExchangeService->fetchFullCaseData($filters);
                
                $debugInfo['raw_data_type'] = gettype($rawData);
                $debugInfo['filters_applied'] = $filters;
                
                // Convert to array if it's an object
                if (is_object($rawData)) {
                    $rawData = json_decode(json_encode($rawData), true);
                }
                
                $debugInfo['converted_data_keys'] = is_array($rawData) ? array_keys($rawData) : 'not_array';
                
                // Handle different response structures - be more flexible
                $cases = $this->extractCasesFromResponse($rawData);
                
                $debugInfo['final_cases_count'] = count($cases);
                $debugInfo['sample_case'] = !empty($cases) ? array_slice($cases, 0, 1) : null;

            } catch (\Exception $e) {
                $debugInfo = [
                    'error' => $e->getMessage(),
                    'filters_applied' => $filters,
                    'stack_trace' => $e->getTraceAsString()
                ];
                Log::error('Failed to load cases: ' . $e->getMessage());
                
                // Add sample data for testing when API fails (only in debug mode)
                if (config('app.debug', false)) {
                    $cases = $this->getSampleCases();
                    $debugInfo['using_sample_data'] = true;
                }
            }

            // Enable debug info only when needed (set to false for production)
            $debugInfo['view_debug'] = config('app.debug', false);

            return view('data-exchange.cases.index', compact('cases', 'loading', 'debugInfo', 'outletActivities'));
        } catch (\Exception $e) {
            return view('data-exchange.cases.index', [
                'cases' => [],
                'loading' => false,
                'debugInfo' => ['controller_error' => $e->getMessage()],
                'outletActivities' => []
            ])->with('error', 'Failed to load cases: ' . $e->getMessage());
        }
    }

    /**
     * Extract cases from various response structures
     */
    private function extractCasesFromResponse($data)
    {
        if (empty($data)) {
            return [];
        }

        // If it's already an array of cases (has numeric keys)
        if (is_array($data) && isset($data[0])) {
            return $data;
        }

        // Check various nested structures
        $possiblePaths = [
            'Cases.Case',
            'Case',
            'cases',
            'data'
        ];

        foreach ($possiblePaths as $path) {
            $result = data_get($data, $path);
            if (!empty($result)) {
                // If single item, wrap in array
                if (!is_array($result) || (is_array($result) && !isset($result[0]))) {
                    return [$result];
                }
                return $result;
            }
        }

        // If we have some data but couldn't extract it through normal paths
        if (is_array($data)) {
            // Check if this looks like a single case record
            if (isset($data['case_id']) || isset($data['CaseId']) || 
                isset($data['client_id']) || isset($data['ClientId'])) {
                return [$data];
            }
        }

        return [];
    }

    /**
     * Show sessions index page
     */
    public function sessionsIndex(Request $request)
    {
        try {
            $filters = $this->buildFilters($request);
            
            // For sessions, set a default date range to get some data
            if (empty($filters['case_id']) && !$request->has('case_id') && empty($request->all())) {
                $filters['date_from'] = now()->subDays(30)->format('Y-m-d');
            }
            
            $loading = false;
            $sessions = [];
            $debugInfo = [];
            $serviceTypes = [];

            // Get service types for filter dropdown (if available)
            try {
                $serviceTypes = [
                    (object)['ServiceTypeId' => '5', 'ServiceTypeName' => 'Counselling'],
                    (object)['ServiceTypeId' => '1', 'ServiceTypeName' => 'Assessment'],
                    (object)['ServiceTypeId' => '2', 'ServiceTypeName' => 'Support Group'],
                    (object)['ServiceTypeId' => '3', 'ServiceTypeName' => 'Case Management'],
                ];
            } catch (\Exception $e) {
                Log::error('Failed to load service types: ' . $e->getMessage());
            }

            // Load data with filters
            try {
                $rawData = $this->dataExchangeService->fetchFullSessionData($filters);
                
                $debugInfo['raw_data_type'] = gettype($rawData);
                $debugInfo['filters_applied'] = $filters;
                
                // Convert to array if it's an object
                if (is_object($rawData)) {
                    $rawData = json_decode(json_encode($rawData), true);
                }
                
                $debugInfo['converted_data_keys'] = is_array($rawData) ? array_keys($rawData) : 'not_array';
                
                // Handle different response structures - be more flexible
                $sessions = $this->extractSessionsFromResponse($rawData);
                
                $debugInfo['final_sessions_count'] = count($sessions);
                $debugInfo['sample_session'] = !empty($sessions) ? array_slice($sessions, 0, 1) : null;

            } catch (\Exception $e) {
                $debugInfo = [
                    'error' => $e->getMessage(),
                    'filters_applied' => $filters,
                    'stack_trace' => $e->getTraceAsString()
                ];
                Log::error('Failed to load sessions: ' . $e->getMessage());
                
                // Add sample data for testing when API fails (only in debug mode)
                if (config('app.debug', false)) {
                    $sessions = $this->getSampleSessions();
                    $debugInfo['using_sample_data'] = true;
                }
            }

            // Enable debug info only when needed (set to false for production)
            $debugInfo['view_debug'] = config('app.debug', false);

            return view('data-exchange.sessions.index', compact('sessions', 'loading', 'debugInfo', 'serviceTypes'));
        } catch (\Exception $e) {
            return view('data-exchange.sessions.index', [
                'sessions' => [],
                'loading' => false,
                'debugInfo' => ['controller_error' => $e->getMessage()],
                'serviceTypes' => []
            ])->with('error', 'Failed to load sessions: ' . $e->getMessage());
        }
    }

    /**
     * Extract sessions from various response structures
     */
    private function extractSessionsFromResponse($data)
    {
        if (empty($data)) {
            return [];
        }

        // If it's already an array of sessions (has numeric keys)
        if (is_array($data) && isset($data[0])) {
            return $data;
        }

        // Check various nested structures
        $possiblePaths = [
            'Sessions.Session',
            'Session',
            'sessions',
            'data'
        ];

        foreach ($possiblePaths as $path) {
            $result = data_get($data, $path);
            if (!empty($result)) {
                // If single item, wrap in array
                if (!is_array($result) || (is_array($result) && !isset($result[0]))) {
                    return [$result];
                }
                return $result;
            }
        }

        // If we have some data but couldn't extract it through normal paths
        if (is_array($data)) {
            // Check if this looks like a single session record
            if (isset($data['session_id']) || isset($data['SessionId']) || 
                isset($data['case_id']) || isset($data['CaseId'])) {
                return [$data];
            }
        }

        return [];
    }

    /**
     * Get sample clients for testing table component
     */
    private function getSampleClients()
    {
        return [
            [
                'client_id' => 'CLIENT_001',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'date_of_birth' => '1990-05-15',
                'gender' => 'M',
                'state' => 'NSW',
                'postal_code' => '2000',
                'client_type' => 'Individual',
                'suburb' => 'Sydney',
                'country_of_birth' => 'AUS',
                'primary_language' => 'ENG',
                'indigenous_status' => 'N'
            ],
            [
                'client_id' => 'CLIENT_002',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'date_of_birth' => '1985-09-22',
                'gender' => 'F',
                'state' => 'VIC',
                'postal_code' => '3000',
                'client_type' => 'Individual',
                'suburb' => 'Melbourne',
                'country_of_birth' => 'AUS',
                'primary_language' => 'ENG',
                'indigenous_status' => 'N'
            ],
            [
                'client_id' => 'CLIENT_003',
                'first_name' => 'Bob',
                'last_name' => 'Wilson',
                'date_of_birth' => '1978-12-03',
                'gender' => 'M',
                'state' => 'QLD',
                'postal_code' => '4000',
                'client_type' => 'Family',
                'suburb' => 'Brisbane',
                'country_of_birth' => 'UK',
                'primary_language' => 'ENG',
                'indigenous_status' => 'U'
            ]
        ];
    }

    /**
     * Get sample cases for testing table component
     */
    private function getSampleCases()
    {
        return [
            [
                'case_id' => 'CASE_001',
                'client_id' => 'CLIENT_001',
                'outlet_activity_id' => '61936',
                'referral_source_code' => 'SELF',
                'client_attendance_profile_code' => 'INDIVIDUAL',
                'end_date' => '2024-01-15',
                'exit_reason_code' => 'COMPLETED',
                'total_unidentified_clients' => 0
            ],
            [
                'case_id' => 'CASE_002',
                'client_id' => 'CLIENT_002',
                'outlet_activity_id' => '61937',
                'referral_source_code' => 'GP',
                'client_attendance_profile_code' => 'FAMILY',
                'end_date' => null,
                'exit_reason_code' => null,
                'total_unidentified_clients' => 1
            ],
            [
                'case_id' => 'CASE_003',
                'client_id' => 'CLIENT_003',
                'outlet_activity_id' => '61936',
                'referral_source_code' => 'COMMUNITY',
                'client_attendance_profile_code' => 'PSGROUP',
                'end_date' => '2023-12-20',
                'exit_reason_code' => 'VOLUNTARY',
                'total_unidentified_clients' => 0
            ]
        ];
    }

    /**
     * Get sample sessions for testing table component
     */
    private function getSampleSessions()
    {
        return [
            [
                'session_id' => 'SESSION_001',
                'case_id' => 'CASE_001',
                'service_type_id' => '5',
                'session_date' => '2024-01-10',
                'duration_minutes' => 60,
                'session_status' => 'Completed',
                'location' => 'Office Room 1',
                'outcome' => 'Positive',
                'notes' => 'Initial counselling session went well. Client is engaged.'
            ],
            [
                'session_id' => 'SESSION_002',
                'case_id' => 'CASE_002',
                'service_type_id' => '1',
                'session_date' => '2024-01-12',
                'duration_minutes' => 45,
                'session_status' => 'Scheduled',
                'location' => 'Community Center',
                'outcome' => 'Ongoing',
                'notes' => 'Assessment session scheduled for next week.'
            ],
            [
                'session_id' => 'SESSION_003',
                'case_id' => 'CASE_001',
                'service_type_id' => '5',
                'session_date' => '2024-01-08',
                'duration_minutes' => 90,
                'session_status' => 'No Show',
                'location' => 'Office Room 2',
                'outcome' => 'Challenging',
                'notes' => 'Client did not attend scheduled session.'
            ]
        ];
    }

    // API Methods for AJAX frontend operations
    
    /**
     * Update a client via API
     */
    public function apiUpdateClient(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'given_name' => 'required|string|max:255',
            'family_name' => 'required|string|max:255',
            'birth_date' => 'required|date',
            'gender_code' => 'required|in:MALE,FEMALE,OTHER,NOTSTATED'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Transform form data to API format
            $apiData = [
                'GivenName' => $request->given_name,
                'FamilyName' => $request->family_name,
                'BirthDate' => $request->birth_date,
                'GenderCode' => $request->gender_code,
            ];
            
            $result = $this->dataExchangeService->updateClient($id, $apiData);
            
            return response()->json([
                'success' => true,
                'message' => 'Client updated successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update client: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a client via API
     */
    public function apiDeleteClient($id)
    {
        try {
            $result = $this->dataExchangeService->deleteClient($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Client deleted successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete client: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update a case via API
     */
    public function apiUpdateCase(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'referral_source_code' => 'required|string|max:50',
            'client_attendance_profile_code' => 'sometimes|string|max:50'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Transform form data to API format
            $apiData = [
                'ReferralSourceCode' => $request->referral_source_code,
                'ClientAttendanceProfileCode' => $request->client_attendance_profile_code,
            ];
            
            $result = $this->dataExchangeService->updateCase($id, $apiData);
            
            return response()->json([
                'success' => true,
                'message' => 'Case updated successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update case: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a case via API
     */
    public function apiDeleteCase($id)
    {
        try {
            $result = $this->dataExchangeService->deleteCase($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Case deleted successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete case: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update a session via API
     */
    public function apiUpdateSession(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'session_date' => 'required|date',
            'topic_code' => 'sometimes|string|max:50',
            'time' => 'sometimes|string|max:100',
            'case_id' => 'required|string|max:50' // Required for session updates
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Transform form data to API format
            $apiData = [
                'SessionDate' => $request->session_date,
                'TopicCode' => $request->topic_code,
                'Time' => $request->time,
                'CaseId' => $request->case_id,
            ];
            
            $result = $this->dataExchangeService->updateSession($id, $apiData);
            
            return response()->json([
                'success' => true,
                'message' => 'Session updated successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update session: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a session via API
     */
    public function apiDeleteSession($id)
    {
        try {
            $result = $this->dataExchangeService->deleteSession($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Session deleted successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete session: ' . $e->getMessage()
            ], 500);
        }
    }
}
