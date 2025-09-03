<?php

namespace App\Services;

use App\Services\SoapClientService;
use Illuminate\Support\Facades\Log;

class DataExchangeService
{
    protected $soapClient;

    public function __construct(SoapClientService $soapClient)
    {
        $this->soapClient = $soapClient;
    }

    /**
     * Submit client data to DSS Data Exchange
     */
    public function submitClientData($clientData)
    {
        $parameters = [
            'OrganisationID' => config('soap.dss.organisation_id'),
            'ClientData' => $this->formatClientData($clientData)
        ];

        return $this->soapClient->call('SubmitClientData', $parameters);
    }

    /**
     * Get data exchange status
     */
    public function getSubmissionStatus($submissionId)
    {
        $parameters = [
            'OrganisationID' => config('soap.dss.organisation_id'),
            'SubmissionID' => $submissionId
        ];

        return $this->soapClient->call('GetSubmissionStatus', $parameters);
    }

    /**
     * Validate client data before submission
     */
    public function validateClientData($clientData)
    {
        $parameters = [
            'OrganisationID' => config('soap.dss.organisation_id'),
            'ClientData' => $this->formatClientData($clientData)
        ];

        return $this->soapClient->call('ValidateClientData', $parameters);
    }

    /**
     * Get available data exchange services
     */
    public function getAvailableServices()
    {
        $parameters = [
            'OrganisationID' => config('soap.dss.organisation_id')
        ];

        return $this->soapClient->call('GetAvailableServices', $parameters);
    }

    /**
     * Format client data according to DSS specifications
     */
    protected function formatClientData($data)
    {
        return [
            'ClientID' => $data['client_id'] ?? null,
            'FirstName' => $data['first_name'] ?? null,
            'LastName' => $data['last_name'] ?? null,
            'DateOfBirth' => $data['date_of_birth'] ?? null,
            'Gender' => $data['gender'] ?? null,
            'IndigenousStatus' => $data['indigenous_status'] ?? null,
            'CountryOfBirth' => $data['country_of_birth'] ?? null,
            'PostalCode' => $data['postal_code'] ?? null,
            'PrimaryLanguage' => $data['primary_language'] ?? null,
            'InterpreterRequired' => $data['interpreter_required'] ?? false,
            'DisabilityFlag' => $data['disability_flag'] ?? false,
            'ClientType' => $data['client_type'] ?? null,
        ];
    }

    /**
     * Format service data according to DSS specifications
     */
    protected function formatServiceData($data)
    {
        return [
            'ClientID' => $data['client_id'] ?? null,
            'ServiceType' => $data['service_type'] ?? null,
            'ServiceStartDate' => $data['service_start_date'] ?? null,
            'ServiceEndDate' => $data['service_end_date'] ?? null,
            'ServiceOutcome' => $data['service_outcome'] ?? null,
            'ServiceLocation' => $data['service_location'] ?? null,
            'ServiceProvider' => $data['service_provider'] ?? null,
            'FundingSource' => $data['funding_source'] ?? null,
            'ServiceUnits' => $data['service_units'] ?? null,
        ];
    }

    /**
     * Test connection and get available methods
     */
    public function testConnection()
    {
        return $this->soapClient->testConnection();
    }

    /**
     * Get available SOAP functions from the service
     */
    public function getAvailableFunctions()
    {
        return $this->soapClient->getFunctions();
    }

    /**
     * Test basic API connectivity with Ping
     */
    public function ping()
    {
        try {
            $result = $this->soapClient->call('Ping', []);

            return [
                'status' => 'success',
                'message' => 'Ping successful',
                'result' => $result,
                'request' => $this->soapClient->getLastRequest(),
                'response' => $this->soapClient->getLastResponse()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'request' => $this->soapClient->getLastRequest(),
                'response' => $this->soapClient->getLastResponse()
            ];
        }
    }

    /**
     * Get last SOAP request for debugging
     */
    public function getLastRequest()
    {
        return $this->soapClient ? $this->soapClient->getLastRequest() : null;
    }

    /**
     * Get last SOAP response for debugging
     */
    public function getLastResponse()
    {
        return $this->soapClient ? $this->soapClient->getLastResponse() : null;
    }

    /**
     * Get sanitized last SOAP request (safe for web display)
     */
    public function getSanitizedLastRequest()
    {
        return $this->soapClient ? $this->soapClient->getSanitizedLastRequest() : 'SOAP client not initialized';
    }

    /**
     * Get sanitized last SOAP response (safe for web display)
     */
    public function getSanitizedLastResponse()
    {
        return $this->soapClient ? $this->soapClient->getSanitizedLastResponse() : 'SOAP client not initialized';
    }

    /**
     * Bulk submit multiple client records
     */
    public function bulkSubmitClientData($clientDataArray)
    {
        $results = [];

        foreach ($clientDataArray as $index => $clientData) {
            try {
                $result = $this->submitClientData($clientData);
                $results[$index] = [
                    'status' => 'success',
                    'result' => $result
                ];
            } catch (\Exception $e) {
                $results[$index] = [
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Generate sample client data for testing
     */
    public function generateSampleClientData()
    {
        return [
            'client_id' => 'TEST001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
            'gender' => 'M',
            'indigenous_status' => 'N',
            'country_of_birth' => 'Australia',
            'postal_code' => '2000',
            'primary_language' => 'English',
            'interpreter_required' => false,
            'disability_flag' => false,
            'client_type' => 'Individual'
        ];
    }

    /**
     * Generate sample service data for testing
     */
    public function generateSampleServiceData()
    {
        return [
            'client_id' => 'TEST001',
            'service_type' => 'Counselling',
            'service_start_date' => '2024-01-01',
            'service_end_date' => '2024-01-31',
            'service_outcome' => 'Completed',
            'service_location' => 'Sydney',
            'service_provider' => 'Test Provider',
            'funding_source' => 'Government',
            'service_units' => 10
        ];
    }

    /**
     * Retrieve client data from DSS Data Exchange
     */
    public function getClientData($filters = [])
    {
        $criteria = $this->formatSearchCriteria($filters);
        $parameters = [
            'Criteria' => $criteria
        ];

        // Log the exact parameters being sent
        Log::info('SearchClient Request Parameters:', [
            'filters_received' => $filters,
            'formatted_criteria' => $criteria,
            'full_parameters' => $parameters
        ]);

        return $this->soapClient->call('SearchClient', $parameters);
    }


    /**
     * Retrieve client by ID
     * Note: ClientId must be passed outside the Criteria array as per DSS requirements
     */
    public function getClientById($clientId)
    {
        // ClientId must be at top level, separate from Criteria array
        $parameters = [
            'ClientId' => $clientId,
            'Criteria' => []  // Empty criteria array as the ID is the main selector
        ];

        // Log the exact parameters being sent
        Log::info('GetClient Request Parameters:', [
            'client_id_received' => $clientId,
            'full_parameters' => $parameters
        ]);

        return $this->soapClient->call('GetClient', $parameters);
    }

    /**
     * Search cases using SearchCase SOAP method
     */
    public function getCaseData($filters = [])
    {
        $criteria = $this->formatCaseSearchCriteria($filters);
        $parameters = [
            'Criteria' => $criteria
        ];

        // Log the exact parameters being sent
        Log::info('SearchCase Request Parameters:', [
            'filters_received' => $filters,
            'formatted_criteria' => $criteria,
            'full_parameters' => $parameters
        ]);

        return $this->soapClient->call('SearchCase', $parameters);
    }

    /**
     * Get case by ID
     */
    public function getCaseById($caseId)
    {
        $parameters = [
            'CaseId' => $caseId
        ];

        return $this->soapClient->call('GetCase', $parameters);
    }

    /**
     * Get sessions/services data - Sessions are linked to Cases and require a Case ID
     */
    public function getSessionData($filters = [])
    {
        // Debug logging
        Log::info('getSessionData called with filters:', [
            'filters' => $filters,
            'case_id_present' => isset($filters['case_id']),
            'case_id_value' => $filters['case_id'] ?? 'not set',
            'case_id_empty' => empty($filters['case_id'])
        ]);

        // Check if a Case ID is provided - this is required for session retrieval
        if (!empty($filters['case_id'])) {
            Log::info('Getting sessions for specific case: ' . $filters['case_id']);
            // Use SearchCase to get the case data, which may include session information
            $caseFilters = ['case_id' => $filters['case_id']];
            $caseResult = $this->getCaseData($caseFilters);

            // Check if the case result contains session data
            // Convert to array if it's an object for consistent handling
            if (is_object($caseResult)) {
                $caseResult = json_decode(json_encode($caseResult), true);
            }

            if (isset($caseResult['Sessions']) || isset($caseResult['SessionData'])) {
                Log::info('Found session data in case result');
                return $caseResult;
            }

            // If no session data found in case, return informative message
            Log::info('No session data found for case: ' . $filters['case_id']);
            return [
                'message' => 'No session data found for Case ID: ' . $filters['case_id'],
                'case_id' => $filters['case_id'],
                'note' => 'This case may not have any associated sessions, or sessions may need to be retrieved individually using session IDs.'
            ];
        }

        // If no Case ID provided, return an informative error with guidance
        throw new \Exception('A Case ID is required to retrieve session data. Sessions in the DSS system are linked to specific cases. Please provide a Case ID to get sessions, or use "Get Sessions for Case" option instead. Filters received: ' . json_encode($filters));
    }


    /**
     * Get session by ID
     * Note: Both CaseId and SessionId must be passed outside the Criteria array as per DSS requirements
     */
    public function getSessionById($sessionId, $caseId = null)
    {
        // Both CaseId and SessionId must be at top level, separate from Criteria array
        $parameters = [
            'SessionId' => $sessionId,
            'Criteria' => []  // Empty criteria array as the IDs are the main selectors
        ];
        
        // Add CaseId if provided (required for DSS API)
        if ($caseId) {
            $parameters['CaseId'] = $caseId;
        }

        // Log the exact parameters being sent
        Log::info('GetSession Request Parameters:', [
            'session_id_received' => $sessionId,
            'case_id_received' => $caseId,
            'full_parameters' => $parameters
        ]);

        return $this->soapClient->call('GetSession', $parameters);
    }




    /**
     * Retrieve services for a specific client
     * Note: Services are called "Sessions" and are linked via Cases in DSS system
     */
    public function getClientServices($clientId, $filters = [])
    {
        // Search for cases related to this client first
        $searchFilters = array_merge($filters, ['ClientID' => $clientId]);
        $parameters = [
            'OrganisationID' => config('soap.dss.organisation_id'),
            'Filters' => $this->formatFilters($searchFilters)
        ];

        return $this->soapClient->call('SearchCase', $parameters);
    }

    /**
     * Get data export in specified format
     */
    public function exportData($resourceType, $filters = [], $format = 'xml')
    {
        $parameters = [
            'OrganisationID' => config('soap.dss.organisation_id'),
            'ResourceType' => $resourceType,
            'Filters' => $this->formatFilters($filters),
            'Format' => strtoupper($format)
        ];

        return $this->soapClient->call('ExportData', $parameters);
    }


    /**
     * Get data schema for a resource type
     * Since GetResourceSchema may not exist, provide helpful schema information
     */
    public function getResourceSchema($resourceType)
    {
        // Try multiple possible method names for schema information
        $possibleMethods = [
            'GetResourceSchema',
            'GetSchema',
            'DescribeResource',
            'GetAvailableServices'
        ];

        $parameters = [
            'OrganisationID' => config('soap.dss.organisation_id'),
            'ResourceType' => $resourceType
        ];

        // Try each method until one works
        foreach ($possibleMethods as $method) {
            try {
                return $this->soapClient->call($method, $parameters);
            } catch (\Exception) {
                continue; // Try next method
            }
        }

        // If no schema methods work, return helpful information based on resource type
        return $this->getDefaultSchemaInfo($resourceType);
    }

    /**
     * Provide default schema information when SOAP schema methods don't exist
     */
    protected function getDefaultSchemaInfo($resourceType)
    {
        $schemas = [
            'clients' => [
                'method' => 'SearchClient',
                'description' => 'Search for client records in the DSS system',
                'required_parameters' => [
                    'PageIndex' => 'Page number (1-based)',
                    'PageSize' => 'Number of records per page (default: 100)',
                    'SortColumn' => 'Column to sort by (default: ClientId)',
                    'IsAscending' => 'Sort direction (default: true)'
                ],
                'optional_parameters' => [
                    'ClientId' => 'Specific client ID to search for',
                    'GivenName' => 'Client first name',
                    'FamilyName' => 'Client last name',
                    'CreatedDateFrom' => 'Search from date (ISO format)',
                    'CreatedDateTo' => 'Search to date (ISO format)'
                ]
            ],
            'cases' => [
                'method' => 'SearchCase',
                'description' => 'Search for case records in the DSS system',
                'required_parameters' => [
                    'PageIndex' => 'Page number (1-based)',
                    'PageSize' => 'Number of records per page (default: 100)',
                    'SortColumn' => 'Column to sort by (default: CaseId)',
                    'IsAscending' => 'Sort direction (default: true)'
                ],
                'optional_parameters' => [
                    'CaseId' => 'Specific case ID to search for',
                    'ClientId' => 'Client ID associated with cases',
                    'CaseStatus' => 'Status of the case',
                    'CaseType' => 'Type of case',
                    'CreatedDateFrom' => 'Search from date (ISO format)',
                    'CreatedDateTo' => 'Search to date (ISO format)'
                ]
            ],
            'sessions' => [
                'method' => 'SearchSession (with SearchCase fallback)',
                'description' => 'Search for session records in the DSS system',
                'required_parameters' => [
                    'PageIndex' => 'Page number (1-based)',
                    'PageSize' => 'Number of records per page (default: 100)',
                    'SortColumn' => 'Column to sort by (default: SessionId)',
                    'IsAscending' => 'Sort direction (default: true)'
                ],
                'optional_parameters' => [
                    'SessionId' => 'Specific session ID to search for',
                    'CaseId' => 'Case ID associated with sessions',
                    'ClientId' => 'Client ID associated with sessions',
                    'SessionType' => 'Type of session',
                    'SessionStatus' => 'Status of the session',
                    'SessionDateFrom' => 'Search from date (ISO format)',
                    'SessionDateTo' => 'Search to date (ISO format)'
                ]
            ]
        ];

        return $schemas[$resourceType] ?? [
            'error' => 'Schema information not available for this resource type',
            'suggestion' => 'Use the "View Available Methods" feature to see what SOAP methods are available'
        ];
    }

    /**
     * Format filters for API calls
     */
    protected function formatFilters($filters)
    {
        $formattedFilters = [];

        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                $formattedFilters[] = [
                    'Field' => $key,
                    'Value' => $value,
                    'Operator' => $filters[$key . '_operator'] ?? 'equals'
                ];
            }
        }

        return $formattedFilters;
    }

    /**
     * Format search criteria for SearchClient calls
     */
    protected function formatSearchCriteria($filters)
    {
        $criteria = [];

        // Required pagination parameters (from SearchCriteriaBase)
        $criteria['PageIndex'] = $filters['page_index'] ?? 1; // 1-based page index
        $criteria['PageSize'] = $filters['page_size'] ?? 100; // Default 100 records per page
        $criteria['IsAscending'] = $filters['is_ascending'] ?? true; // Default ascending sort

        // Required sort column (from ClientSearchCriteriaBase)
        $criteria['SortColumn'] = $filters['sort_column'] ?? 'ClientId'; // Default sort by ClientId

        // Map common filters to DSS ClientSearchCriteria fields (all optional)
        if (!empty($filters['client_id'])) {
            $criteria['ClientId'] = $filters['client_id'];
        }
        if (!empty($filters['first_name'])) {
            $criteria['GivenName'] = $filters['first_name']; // DSS uses GivenName
        }
        if (!empty($filters['last_name'])) {
            $criteria['FamilyName'] = $filters['last_name']; // DSS uses FamilyName
        }
        if (!empty($filters['date_from'])) {
            $criteria['CreatedDateFrom'] = $filters['date_from'] . 'T00:00:00'; // Add time component
        }
        if (!empty($filters['date_to'])) {
            $criteria['CreatedDateTo'] = $filters['date_to'] . 'T23:59:59'; // Add time component
        }

        return $criteria;
    }

    /**
     * Format search criteria for SearchCase calls
     */
    protected function formatCaseSearchCriteria($filters)
    {
        $criteria = [];

        // Required pagination parameters (from SearchCriteriaBase)
        $criteria['PageIndex'] = $filters['page_index'] ?? 1; // 1-based page index
        $criteria['PageSize'] = $filters['page_size'] ?? 100; // Default 100 records per page
        $criteria['IsAscending'] = $filters['is_ascending'] ?? true; // Default ascending sort

        // Required sort column (from CaseSearchCriteriaBase)
        $criteria['SortColumn'] = $filters['sort_column'] ?? 'CaseId'; // Default sort by CaseId

        // Map common filters to DSS CaseSearchCriteria fields (all optional)
        if (!empty($filters['case_id'])) {
            $criteria['CaseId'] = $filters['case_id'];
        }
        if (!empty($filters['client_id'])) {
            $criteria['ClientId'] = $filters['client_id'];
        }
        if (!empty($filters['case_status'])) {
            $criteria['CaseStatus'] = $filters['case_status'];
        }
        if (!empty($filters['case_type'])) {
            $criteria['CaseType'] = $filters['case_type'];
        }
        if (!empty($filters['date_from'])) {
            $criteria['CreatedDateFrom'] = $filters['date_from'] . 'T00:00:00'; // Add time component
        }
        if (!empty($filters['date_to'])) {
            $criteria['CreatedDateTo'] = $filters['date_to'] . 'T23:59:59'; // Add time component
        }
        if (!empty($filters['service_start_date'])) {
            $criteria['ServiceStartDateFrom'] = $filters['service_start_date'] . 'T00:00:00';
        }
        if (!empty($filters['service_end_date'])) {
            $criteria['ServiceEndDateTo'] = $filters['service_end_date'] . 'T23:59:59';
        }

        return $criteria;
    }

    /**
     * Format search criteria for SearchSession calls
     */
    protected function formatSessionSearchCriteria($filters)
    {
        $criteria = [];

        // Required pagination parameters (from SearchCriteriaBase)
        $criteria['PageIndex'] = $filters['page_index'] ?? 1; // 1-based page index
        $criteria['PageSize'] = $filters['page_size'] ?? 100; // Default 100 records per page
        $criteria['IsAscending'] = $filters['is_ascending'] ?? true; // Default ascending sort

        // Required sort column (from SessionSearchCriteriaBase)
        $criteria['SortColumn'] = $filters['sort_column'] ?? 'SessionId'; // Default sort by SessionId

        // Map common filters to DSS SessionSearchCriteria fields (all optional)
        if (!empty($filters['session_id'])) {
            $criteria['SessionId'] = $filters['session_id'];
        }
        if (!empty($filters['case_id'])) {
            $criteria['CaseId'] = $filters['case_id'];
        }
        if (!empty($filters['client_id'])) {
            $criteria['ClientId'] = $filters['client_id'];
        }
        if (!empty($filters['session_type'])) {
            $criteria['SessionType'] = $filters['session_type'];
        }
        if (!empty($filters['service_type'])) {
            $criteria['ServiceType'] = $filters['service_type'];
        }
        if (!empty($filters['session_status'])) {
            $criteria['SessionStatus'] = $filters['session_status'];
        }
        if (!empty($filters['date_from'])) {
            $criteria['SessionDateFrom'] = $filters['date_from'] . 'T00:00:00'; // Add time component
        }
        if (!empty($filters['date_to'])) {
            $criteria['SessionDateTo'] = $filters['date_to'] . 'T23:59:59'; // Add time component
        }
        if (!empty($filters['service_start_date'])) {
            $criteria['ServiceStartDate'] = $filters['service_start_date'] . 'T00:00:00';
        }
        if (!empty($filters['service_end_date'])) {
            $criteria['ServiceEndDate'] = $filters['service_end_date'] . 'T23:59:59';
        }

        return $criteria;
    }

    /**
     * Convert data to specified format
     */
    public function convertDataFormat($data, $format = 'json')
    {
        // Convert objects to arrays for consistent processing
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }

        switch (strtolower($format)) {
            case 'json':
                return json_encode($data, JSON_PRETTY_PRINT);

            case 'xml':
                return $this->arrayToXml($data);

            case 'csv':
                return $this->arrayToCsv($this->extractRecordsForCsv($data));

            default:
                return $data;
        }
    }

    /**
     * Extract records from SOAP response for CSV conversion
     */
    protected function extractRecordsForCsv($data)
    {
        if (!is_array($data)) {
            return [];
        }

        // Handle different SOAP response structures
        if (isset($data['Clients']['Client'])) {
            // Client data response
            $records = $data['Clients']['Client'];
            return is_array($records) ? $records : [$records];
        }

        if (isset($data['Sessions']['Session'])) {
            // Session/Service data response
            $records = $data['Sessions']['Session'];
            return is_array($records) ? $records : [$records];
        }

        if (isset($data['Sessions']) && isset($data['Source'])) {
            // Session data extracted from cases
            $records = $data['Sessions'];
            return is_array($records) ? $records : [$records];
        }

        if (isset($data['Cases']['Case'])) {
            // Case data response
            $records = $data['Cases']['Case'];
            return is_array($records) ? $records : [$records];
        }

        if (isset($data['Cases']) && isset($data['Source'])) {
            // Case data used as fallback for sessions
            $records = $data['Cases'];
            return is_array($records) ? $records : [$records];
        }

        // Check if data is already in the correct format (array of records)
        if (is_array($data) && !empty($data)) {
            $firstElement = reset($data);
            if (is_array($firstElement) && array_keys($data) === range(0, count($data) - 1)) {
                // Already an indexed array of records
                return $data;
            }
        }

        // Fallback - return the data as is
        return $data;
    }

    /**
     * Convert array to XML
     */
    protected function arrayToXml($data, $rootElement = 'Data', $xml = null)
    {
        if ($xml === null) {
            $xml = new \SimpleXMLElement("<?xml version='1.0' encoding='UTF-8'?><{$rootElement}></{$rootElement}>");
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->arrayToXml($value, $key, $xml->addChild($key));
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }

        return $xml->asXML();
    }

    /**
     * Convert array to CSV
     */
    protected function arrayToCsv($data)
    {
        if (empty($data) || !is_array($data)) {
            return "No data available\n";
        }

        $output = '';
        $headers = [];

        // Normalize data - ensure it's an array of arrays
        $normalizedData = [];

        // Check if data is associative array (single record) or indexed array (multiple records)
        if (array_keys($data) !== range(0, count($data) - 1)) {
            // Single associative array - convert to array of arrays
            $normalizedData = [$data];
        } else {
            // Check if first element is an array (multiple records)
            if (is_array(reset($data))) {
                $normalizedData = $data;
            } else {
                // Array of scalar values - convert to single record
                $normalizedData = [$data];
            }
        }

        // Extract headers from first row
        if (!empty($normalizedData)) {
            $firstRow = reset($normalizedData);
            if (is_array($firstRow)) {
                $headers = array_keys($firstRow);
                $output .= implode(',', array_map(function ($header) {
                    return '"' . str_replace('"', '""', $header) . '"';
                }, $headers)) . "\n";

                // Process data rows
                foreach ($normalizedData as $row) {
                    if (is_array($row)) {
                        $values = [];
                        foreach ($headers as $header) {
                            $value = $row[$header] ?? '';
                            // Convert arrays and objects to string representation
                            if (is_array($value) || is_object($value)) {
                                $value = json_encode($value);
                            }
                            $values[] = '"' . str_replace('"', '""', (string)$value) . '"';
                        }
                        $output .= implode(',', $values) . "\n";
                    }
                }
            } else {
                // Handle case where first row is not an array
                $output = "Data format not supported for CSV export\n";
            }
        } else {
            $output = "No records found\n";
        }

        return $output;
    }

    /**
     * Get available resource types
     */
    public function getAvailableResources()
    {
        return [
            'clients' => 'Client Data',
            'cases' => 'Case Data',
            'sessions' => 'Session Data (requires Case ID)'
        ];
    }

    /**
     * Get available report types
     */
    public function getAvailableReports()
    {
        return [
            'client_summary' => 'Client Summary Report',
            'service_summary' => 'Service Summary Report',
            'submission_log' => 'Submission Log Report',
            'compliance' => 'Compliance Report',
            'performance' => 'Performance Report'
        ];
    }

    /**
     * Submit service data to DSS Data Exchange
     */
    public function submitServiceData($serviceData)
    {
        $parameters = [
            'OrganisationID' => config('soap.dss.organisation_id'),
            'ServiceData' => $this->formatServiceData($serviceData)
        ];

        return $this->soapClient->call('SubmitServiceData', $parameters);
    }

    /**
     * Generate report based on report type and filters
     */
    public function generateReport($reportType, $filters = [])
    {
        $parameters = [
            'OrganisationID' => config('soap.dss.organisation_id'),
            'ReportType' => $reportType,
            'Filters' => $this->formatFilters($filters)
        ];

        return $this->soapClient->call('GenerateReport', $parameters);
    }
}
