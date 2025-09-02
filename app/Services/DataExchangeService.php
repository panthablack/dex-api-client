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
     * Retrieve service data from DSS Data Exchange
     * Note: DSS calls services "Sessions". There's no SearchSession method,
     * only GetSession for individual sessions. This may need case-based searching.
     */
    public function getServiceData($filters = [])
    {
        // Since there's no SearchSession method, we might need to search cases first
        // and then get sessions for those cases. For now, we'll try SearchCase
        // as services are likely linked to cases in the DSS system.
        $parameters = [
            'OrganisationID' => config('soap.dss.organisation_id'),
            'Filters' => $this->formatFilters($filters)
        ];

        return $this->soapClient->call('SearchCase', $parameters);
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
     * Get reporting data
     */
    public function getReportingData($reportType, $parameters = [])
    {
        $params = [
            'OrganisationID' => config('soap.dss.organisation_id'),
            'ReportType' => $reportType,
            'Parameters' => $parameters
        ];

        return $this->soapClient->call('GetReportingData', $params);
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
     * Convert data to specified format
     */
    public function convertDataFormat($data, $format = 'json')
    {
        switch (strtolower($format)) {
            case 'json':
                return json_encode($data, JSON_PRETTY_PRINT);
                
            case 'xml':
                return $this->arrayToXml($data);
                
            case 'csv':
                return $this->arrayToCsv($data);
                
            default:
                return $data;
        }
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
        if (empty($data)) {
            return '';
        }

        $output = '';
        $headers = [];
        
        // Extract headers from first row
        if (is_array($data) && !empty($data)) {
            $firstRow = is_array(reset($data)) ? reset($data) : $data;
            $headers = array_keys($firstRow);
            $output .= implode(',', $headers) . "\n";
            
            // Process data rows
            foreach ($data as $row) {
                if (is_array($row)) {
                    $values = [];
                    foreach ($headers as $header) {
                        $value = $row[$header] ?? '';
                        $values[] = '"' . str_replace('"', '""', $value) . '"';
                    }
                    $output .= implode(',', $values) . "\n";
                }
            }
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
            'services' => 'Service Data', 
            'submissions' => 'Submission History',
            'reports' => 'Reports',
            'organizations' => 'Organization Data'
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