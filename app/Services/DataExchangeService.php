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
        return $this->soapClient->getLastRequest();
    }

    /**
     * Get last SOAP response for debugging
     */
    public function getLastResponse()
    {
        return $this->soapClient->getLastResponse();
    }

    /**
     * Get sanitized last SOAP request (safe for web display)
     */
    public function getSanitizedLastRequest()
    {
        return $this->soapClient->getSanitizedLastRequest();
    }

    /**
     * Get sanitized last SOAP response (safe for web display)
     */
    public function getSanitizedLastResponse()
    {
        return $this->soapClient->getSanitizedLastResponse();
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
     */
    public function getClientById($clientId)
    {
        $parameters = [
            'ClientId' => $clientId
        ];

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
     * Get sessions/services data - Sessions are linked to Cases
     */
    public function getSessionData($filters = [])
    {
        $criteria = $this->formatSessionSearchCriteria($filters);
        $parameters = [
            'Criteria' => $criteria
        ];

        // Log the exact parameters being sent
        Log::info('SearchSession Request Parameters:', [
            'filters_received' => $filters,
            'formatted_criteria' => $criteria,
            'full_parameters' => $parameters
        ]);

        // Note: If SearchSession doesn't exist, we may need to search cases first
        // and then get sessions for those cases
        try {
            return $this->soapClient->call('SearchSession', $parameters);
        } catch (\Exception) {
            // Fallback: Search cases and extract session information
            Log::info('SearchSession failed, falling back to SearchCase');
            return $this->getCaseData($filters);
        }
    }

    /**
     * Get session by ID
     */
    public function getSessionById($sessionId)
    {
        $parameters = [
            'SessionId' => $sessionId
        ];

        return $this->soapClient->call('GetSession', $parameters);
    }

    /**
     * Get all sessions for a specific case
     */
    public function getSessionsForCase($caseId)
    {
        $parameters = [
            'CaseId' => $caseId
        ];

        return $this->soapClient->call('GetSessionsForCase', $parameters);
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
     */
    public function getResourceSchema($resourceType)
    {
        $parameters = [
            'OrganisationID' => config('soap.dss.organisation_id'),
            'ResourceType' => $resourceType
        ];

        return $this->soapClient->call('GetResourceSchema', $parameters);
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
        
        if (isset($data['Cases']['Case'])) {
            // Case data response
            $records = $data['Cases']['Case'];
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
                $output .= implode(',', array_map(function($header) {
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
            'sessions' => 'Session Data'
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
}