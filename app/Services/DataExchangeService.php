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
            'Client' => $this->formatClientData($clientData),
            'HasValidatedForDuplicateClient' => 'true'

        ];

        return $this->soapClient->call('AddClient', $parameters);
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
        $clientData = [
            'ClientId' => $data['client_id'] ?? null,
            'SLK' => $this->generateSLK($data),
            'GivenName' => $data['first_name'] ?? null,
            'FamilyName' => $data['last_name'] ?? null,
            'BirthDate' => $this->formatBirthDate($data['date_of_birth'] ?? null, !empty($data['is_birth_date_estimate'])),
            'IsBirthDateAnEstimate' => !empty($data['is_birth_date_estimate']),
            'GenderCode' => $this->mapGenderCode($data['gender'] ?? null),
            'AboriginalOrTorresStraitIslanderOriginCode' => $this->mapATSICode($data['indigenous_status'] ?? '9'),
            'CountryOfBirthCode' => $this->mapCountryCode($data['country_of_birth'] ?? null),
            'ResidentialAddress' => [
                'Suburb' => $data['suburb'] ?? null,
                'State' => $data['state'] ?? null,
                'Postcode' => $data['postal_code'] ?? null,
                'AddressLine1' => $data['address_line1'] ?? null,
                'AddressLine2' => $data['address_line2'] ?? null,
            ],
            'LanguageSpokenAtHomeCode' => $this->mapLanguageCode($data['primary_language'] ?? null),
            'InterpreterRequired' => !empty($data['interpreter_required']),
            'HasDisabilities' => !empty($data['disability_flag']),
            'ClientType' => $data['client_type'] ?? null,
            'ConsentToProvideDetails' => !empty($data['consent_to_provide_details']),
            'ConsentedForFutureContacts' => !empty($data['consent_to_be_contacted']),
            'IsUsingPsuedonym' => !empty($data['is_using_pseudonym']),
            'HasValidatedForDuplicateClient' => 'true'
        ];

        if ($clientData['HasDisabilities'])
            $clientData['Disabilities'] = $this->formatDisabilities($data);

        return $clientData;
    }

    /**
     * Format date to ISO 8601 format with time component as required by DSS API
     */
    protected function formatDate($date)
    {
        if (empty($date)) {
            return null;
        }

        // If it's already in the correct format, return as is
        if (preg_match('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $date)) {
            return $date;
        }

        // Convert date to ISO 8601 format with time component
        try {
            $dateObj = new \DateTime($date);
            return $dateObj->format('Y-m-d\TH:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Map gender to DSS gender codes
     */
    protected function mapGenderCode($gender)
    {
        $genderMap = [
            'M' => 'MALE',
            'F' => 'FEMALE',
            'X' => 'OTHER',
            '9' => 'NOTSTATED'
        ];

        return $genderMap[strtoupper($gender ?? '')] ?? 'NOTSTATED';
    }

    /**
     * Map ATSI status to DSS codes
     */
    protected function mapATSICode($status)
    {
        $atsiMap = [
            '1' => 'ABORIGINAL',
            '2' => 'TORRES_STRAIT_ISLANDER',
            '3' => 'BOTH',
            '4' => 'NEITHER',
            '9' => 'NOTSTATED'
        ];

        return $atsiMap[$status ?? '9'] ?? 'NOTSTATED';
    }

    /**
     * Generate SLK (Statistical Linkage Key) according to DSS specifications
     * Uses 2nd, 3rd, 5th letters of last name + 2nd, 3rd letters of first name + ddmmyyyy + gender code
     */
    protected function generateSLK($data)
    {
        // Clean names - remove hyphens, apostrophes, spaces, non-alphabetic characters
        $firstName = preg_replace('/[^A-Z]/i', '', strtoupper($data['first_name'] ?? ''));
        $lastName = preg_replace('/[^A-Z]/i', '', strtoupper($data['last_name'] ?? ''));
        
        // Extract letters from last name (2nd, 3rd, 5th positions)
        $lastNamePart = '';
        $lastNamePart .= isset($lastName[1]) ? $lastName[1] : '2'; // 2nd letter or '2' if missing
        $lastNamePart .= isset($lastName[2]) ? $lastName[2] : '2'; // 3rd letter or '2' if missing  
        $lastNamePart .= isset($lastName[4]) ? $lastName[4] : '2'; // 5th letter or '2' if missing
        
        // If name is missing entirely, use '9' for unknown
        if (empty($lastName)) {
            $lastNamePart = '999';
        }
        
        // Extract letters from first name (2nd, 3rd positions)
        $firstNamePart = '';
        $firstNamePart .= isset($firstName[1]) ? $firstName[1] : '2'; // 2nd letter or '2' if missing
        $firstNamePart .= isset($firstName[2]) ? $firstName[2] : '2'; // 3rd letter or '2' if missing
        
        // If name is missing entirely, use '9' for unknown
        if (empty($firstName)) {
            $firstNamePart = '99';
        }
        
        // Format date as ddmmyyyy
        $dob = $data['date_of_birth'] ?? '';
        if ($dob) {
            try {
                $dateObj = new \DateTime($dob);
                $datePart = $dateObj->format('dmY'); // ddmmyyyy format
            } catch (\Exception $e) {
                $datePart = '01011900'; // Default if parsing fails
            }
        } else {
            $datePart = '01011900'; // Default if no date provided
        }
        
        // Map gender to SLK codes (1=Male, 2=Female, 3=Non-binary/Other, 9=Not stated)
        $gender = strtoupper($data['gender'] ?? '');
        $genderCode = match($gender) {
            'M' => '1',
            'F' => '2', 
            'X' => '3',
            default => '9'
        };
        
        return $lastNamePart . $firstNamePart . $datePart . $genderCode;
    }

    /**
     * Format disabilities array according to DSS specifications
     */
    protected function formatDisabilities($data)
    {
        // Return array structure matching ArrayOfStringDisabilities
        // Based on struct: ArrayOfStringDisabilities { string DisabilityCode; }
        return ['learning'];  // Simple array of disability code strings
    }

    /**
     * Map country to DSS country codes
     */
    protected function mapCountryCode($country)
    {
        // For now, default common values. In production, you'd want a full mapping
        $countryMap = [
            'Australia' => '1101',
            'australia' => '1101',
            'AU' => '1101',
            'United Kingdom' => '2102',
            'New Zealand' => '1201'
        ];

        return $countryMap[$country ?? ''] ?? '1101';  // Default to Australia
    }

    /**
     * Map language to DSS language codes
     */
    protected function mapLanguageCode($language)
    {
        // For now, default common values. In production, you'd want a full mapping
        $languageMap = [
            'English' => '1201',
            'english' => '1201',
            'Mandarin' => '7101',
            'Arabic' => '4101',
            'Vietnamese' => '8201'
        ];

        return $languageMap[$language ?? ''] ?? '1201';  // Default to English
    }

    /**
     * Format birth date with special handling for estimates
     */
    protected function formatBirthDate($date, $isEstimate = false)
    {
        if (empty($date)) {
            return null;
        }

        if ($isEstimate) {
            // For estimates, MUST use January 1st (01/01) as day and month
            try {
                $dateObj = new \DateTime($date);
                $year = $dateObj->format('Y');
                return $year . '-01-01T00:00:00';
            } catch (\Exception $e) {
                // If date parsing fails, use a reasonable default year
                return '1990-01-01T00:00:00';
            }
        }

        return $this->formatDate($date);
    }

    /**
     * Format case data according to DSS specifications
     */
    protected function formatCaseData($data)
    {
        return [
            'CaseID' => $data['case_id'] ?? null,
            'ClientID' => $data['client_id'] ?? null,
            'CaseType' => $data['case_type'] ?? null,
            'CaseStatus' => $data['case_status'] ?? null,
            'StartDate' => $this->formatDate($data['start_date'] ?? null),
            'EndDate' => $this->formatDate($data['end_date'] ?? null),
            'CaseWorker' => $data['case_worker'] ?? null,
            'Priority' => $data['priority'] ?? null,
            'Description' => $data['description'] ?? null,
            'Notes' => $data['notes'] ?? null,
        ];
    }

    /**
     * Format session data according to DSS specifications
     */
    protected function formatSessionData($data)
    {
        return [
            'SessionID' => $data['session_id'] ?? null,
            'CaseID' => $data['case_id'] ?? null,
            'SessionType' => $data['session_type'] ?? null,
            'SessionDate' => $this->formatDate($data['session_date'] ?? null),
            'DurationMinutes' => $data['duration_minutes'] ?? null,
            'Location' => $data['location'] ?? null,
            'SessionStatus' => $data['session_status'] ?? null,
            'Notes' => $data['notes'] ?? null,
            'Attendees' => $data['attendees'] ?? null,
            'Outcome' => $data['outcome'] ?? null,
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
                
                // Check if the response contains a failed transaction status
                $transactionStatus = $this->extractTransactionStatus($result);
                
                if ($transactionStatus && $transactionStatus['statusCode'] === 'Failed') {
                    $errorMessage = $transactionStatus['message'] ?? 'Client data submission failed';
                    $results[$index] = [
                        'status' => 'error',
                        'error' => $errorMessage,
                        'result' => $result,
                        'client_data' => $clientData
                    ];
                } else {
                    $results[$index] = [
                        'status' => 'success',
                        'result' => $result,
                        'client_data' => $clientData
                    ];
                }
            } catch (\Exception $e) {
                $results[$index] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'client_data' => $clientData
                ];
            }
        }

        return $results;
    }

    /**
     * Bulk submit multiple case records
     */
    public function bulkSubmitCaseData($caseDataArray)
    {
        $results = [];

        foreach ($caseDataArray as $index => $caseData) {
            try {
                $result = $this->submitCaseData($caseData);
                
                // Check if the response contains a failed transaction status
                $transactionStatus = $this->extractTransactionStatus($result);
                
                if ($transactionStatus && $transactionStatus['statusCode'] === 'Failed') {
                    $errorMessage = $transactionStatus['message'] ?? 'Case data submission failed';
                    $results[$index] = [
                        'status' => 'error',
                        'error' => $errorMessage,
                        'result' => $result,
                        'case_data' => $caseData
                    ];
                } else {
                    $results[$index] = [
                        'status' => 'success',
                        'result' => $result,
                        'case_data' => $caseData
                    ];
                }
            } catch (\Exception $e) {
                $results[$index] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'case_data' => $caseData
                ];
            }
        }

        return $results;
    }

    /**
     * Bulk submit multiple session records
     */
    public function bulkSubmitSessionData($sessionDataArray)
    {
        $results = [];

        foreach ($sessionDataArray as $index => $sessionData) {
            try {
                $result = $this->submitSessionData($sessionData);
                
                // Check if the response contains a failed transaction status
                $transactionStatus = $this->extractTransactionStatus($result);
                
                if ($transactionStatus && $transactionStatus['statusCode'] === 'Failed') {
                    $errorMessage = $transactionStatus['message'] ?? 'Session data submission failed';
                    $results[$index] = [
                        'status' => 'error',
                        'error' => $errorMessage,
                        'result' => $result,
                        'session_data' => $sessionData
                    ];
                } else {
                    $results[$index] = [
                        'status' => 'success',
                        'result' => $result,
                        'session_data' => $sessionData
                    ];
                }
            } catch (\Exception $e) {
                $results[$index] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'session_data' => $sessionData
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
            'is_birth_date_estimate' => false,
            'gender' => 'M',
            'country_of_birth' => 'Australia',
            'suburb' => 'Sydney',
            'state' => 'NSW',
            'postal_code' => '2000',
            'primary_language' => 'English',
            'indigenous_status' => '4',
            'interpreter_required' => false,
            'disability_flag' => false,
            'client_type' => 'Individual',
            'consent_to_provide_details' => true,
            'consent_to_be_contacted' => true,
            'is_using_pseudonym' => false
        ];
    }

    /**
     * Generate sample case data for testing
     */
    public function generateSampleCaseData()
    {
        return [
            'case_id' => 'CASE001',
            'client_id' => 'TEST001',
            'case_type' => 'Individual Support',
            'case_status' => 'Active',
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+6 months')),
            'case_worker' => 'John Smith',
            'priority' => 'Medium',
            'description' => 'Individual support case for client counselling services',
            'notes' => 'Initial case setup for ongoing support services'
        ];
    }

    /**
     * Generate sample session data for testing
     */
    public function generateSampleSessionData()
    {
        return [
            'session_id' => 'SESSION001',
            'case_id' => 'CASE001',
            'session_type' => 'Individual Counselling',
            'session_date' => date('Y-m-d'),
            'duration_minutes' => 60,
            'location' => 'Office Room 1',
            'session_status' => 'Scheduled',
            'notes' => 'Initial counselling session',
            'attendees' => 'Client, Counsellor',
            'outcome' => 'Ongoing'
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
     * Note: CaseId must be passed outside the Criteria array as per DSS requirements
     */
    public function getCaseById($caseId)
    {
        // CaseId must be at top level, separate from Criteria array
        $parameters = [
            'CaseId' => $caseId,
            'Criteria' => []  // Empty criteria array as the ID is the main selector
        ];

        // Log the exact parameters being sent
        Log::info('GetCase Request Parameters:', [
            'case_id_received' => $caseId,
            'full_parameters' => $parameters
        ]);

        return $this->soapClient->call('GetCase', $parameters);
    }

    /**
     * Fetch Full Case Data - Search for cases then get detailed data for each using GetCase
     * This provides richer case information compared to SearchCase alone
     */
    public function fetchFullCaseData($filters = [])
    {
        try {
            // First, search for cases using the existing SearchCase functionality
            Log::info('Fetching full case data - starting with SearchCase', [
                'filters' => $filters
            ]);

            $searchResult = $this->getCaseData($filters);

            // Convert objects to arrays for consistent handling
            if (is_object($searchResult)) {
                $searchResult = json_decode(json_encode($searchResult), true);
            }

            // Handle different response structures
            $cases = [];
            if (isset($searchResult['Cases']['Case'])) {
                $cases = $searchResult['Cases']['Case'];
                // Ensure it's an array (single case comes as object)
                if (!is_array($cases) || (is_array($cases) && isset($cases['CaseId']))) {
                    $cases = [$cases];
                }
            } elseif (is_array($searchResult) && isset($searchResult[0]['CaseId'])) {
                // Already an array of cases
                $cases = $searchResult;
            } elseif (isset($searchResult['CaseId'])) {
                // Single case
                $cases = [$searchResult];
            }

            if (empty($cases)) {
                Log::info('No cases found in search result');
                return [];
            }

            Log::info('Found cases, now fetching full data', [
                'cases_count' => count($cases),
                'first_case_id' => isset($cases[0]['CaseId']) ? $cases[0]['CaseId'] : 'unknown'
            ]);

            // Now get detailed data for each case using GetCase
            $fullCaseData = [];
            $errors = [];
            $successCount = 0;

            foreach ($cases as $index => $caseInfo) {
                $caseId = null;

                // Extract Case ID (should be array after conversion above)
                if (is_array($caseInfo) && isset($caseInfo['CaseId'])) {
                    $caseId = $caseInfo['CaseId'];
                } elseif (is_object($caseInfo)) {
                    // Convert this individual case to array if still an object
                    $caseInfo = json_decode(json_encode($caseInfo), true);
                    $caseId = $caseInfo['CaseId'] ?? null;
                } elseif (is_string($caseInfo)) {
                    $caseId = $caseInfo;
                }

                // Skip invalid data - only process actual case IDs
                if (!$caseId || !is_string($caseId)) {
                    Log::info('Skipping non-case data', [
                        'index' => $index,
                        'case_id' => $caseId,
                        'case_info_type' => gettype($caseInfo),
                        'is_string_case_id' => is_string($caseId)
                    ]);
                    continue;
                }

                try {
                    Log::info('Fetching full data for case', ['case_id' => $caseId]);

                    // Get detailed case data using GetCase
                    $fullCaseResult = $this->getCaseById($caseId);

                    // Extract clean case data from the SOAP response
                    $cleanCaseData = $this->extractCleanCaseData($fullCaseResult);

                    // Only include successfully retrieved cases
                    if ($cleanCaseData) {
                        $fullCaseData[] = $cleanCaseData;
                        $successCount++;
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to get full data for case', [
                        'case_id' => $caseId,
                        'error' => $e->getMessage()
                    ]);

                    // Don't include failed cases in the response
                    $errors[] = [
                        'case_id' => $caseId,
                        'error' => $e->getMessage()
                    ];
                }
            }

            Log::info('Fetch full case data completed', [
                'total_cases_found' => count($cases),
                'successful_fetches' => $successCount,
                'errors' => count($errors)
            ]);

            // Return only successful cases with clean structure
            return $fullCaseData;
        } catch (\Exception $e) {
            Log::error('Failed to fetch full case data', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);

            throw new \Exception('Failed to fetch full case data: ' . $e->getMessage());
        }
    }

    /**
     * Extract clean case data from SOAP GetCase response
     * Only returns successfully retrieved cases with proper structure
     */
    protected function extractCleanCaseData($soapResponse)
    {
        try {
            // Convert objects to arrays
            if (is_object($soapResponse)) {
                $soapResponse = json_decode(json_encode($soapResponse), true);
            }

            // Check for successful transaction
            if (
                isset($soapResponse['TransactionStatus']['TransactionStatusCode']) &&
                $soapResponse['TransactionStatus']['TransactionStatusCode'] !== 'Success'
            ) {

                Log::info('Case retrieval failed', [
                    'status' => $soapResponse['TransactionStatus']['TransactionStatusCode'] ?? 'unknown',
                    'message' => $soapResponse['TransactionStatus']['Messages']['Message']['MessageDescription'] ?? 'No message'
                ]);
                return null;
            }

            // Extract the actual case data
            if (isset($soapResponse['Case']) && $soapResponse['Case'] !== null) {
                return $soapResponse['Case'];
            }

            // If no proper case structure, return null
            Log::info('No case data found in response structure');
            return null;
        } catch (\Exception $e) {
            Log::error('Error extracting clean case data', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Fetch Full Session Data - First fetches all cases using fetchFullCaseData, then gets detailed session data for each session in each case
     * This provides the richest session information by combining SearchCase + GetCase + GetSession calls
     */
    public function fetchFullSessionData($filters = [])
    {
        try {
            // First, get all full case data
            Log::info('Fetching full session data - starting with fetchFullCaseData', [
                'filters' => $filters
            ]);

            $fullCaseData = $this->fetchFullCaseData($filters);

            if (empty($fullCaseData)) {
                Log::info('No cases found, returning empty session array');
                return [];
            }

            Log::info('Found cases, now extracting sessions', [
                'cases_count' => count($fullCaseData)
            ]);

            // Extract all sessions from all cases and fetch detailed session data
            $fullSessionData = [];
            $totalSessions = 0;
            $successfulSessions = 0;
            $errors = [];

            foreach ($fullCaseData as $caseIndex => $caseData) {
                $caseId = $caseData['CaseDetail']['CaseId'] ?? $caseData['CaseId'] ?? null;

                if (!$caseId) {
                    Log::warning('Case without CaseId found', ['case_data_keys' => array_keys($caseData)]);
                    continue;
                }

                // Extract session IDs from the case data
                $sessionIds = $this->extractSessionIdsFromCase($caseData);

                if (empty($sessionIds)) {
                    Log::info('No sessions found in case', ['case_id' => $caseId]);
                    continue;
                }

                Log::info('Processing sessions for case', [
                    'case_id' => $caseId,
                    'session_count' => count($sessionIds)
                ]);

                foreach ($sessionIds as $sessionId) {
                    $totalSessions++;

                    try {
                        Log::info('Fetching full data for session', [
                            'session_id' => $sessionId,
                            'case_id' => $caseId
                        ]);

                        // Get detailed session data using GetSession
                        $fullSessionResult = $this->getSessionById($sessionId, $caseId);

                        // Extract clean session data from the SOAP response
                        $cleanSessionData = $this->extractCleanSessionData($fullSessionResult);

                        // Only include successfully retrieved sessions
                        if ($cleanSessionData) {
                            // Add case context to session data
                            $cleanSessionData['_case_context'] = [
                                'CaseId' => $caseId,
                                'OutletName' => $caseData['OutletName'] ?? null,
                                'ProgramActivityName' => $caseData['ProgramActivityName'] ?? null
                            ];

                            $fullSessionData[] = $cleanSessionData;
                            $successfulSessions++;
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to get full data for session', [
                            'session_id' => $sessionId,
                            'case_id' => $caseId,
                            'error' => $e->getMessage()
                        ]);

                        $errors[] = [
                            'session_id' => $sessionId,
                            'case_id' => $caseId,
                            'error' => $e->getMessage()
                        ];
                    }
                }
            }

            Log::info('Fetch full session data completed', [
                'cases_processed' => count($fullCaseData),
                'total_sessions_found' => $totalSessions,
                'successful_sessions' => $successfulSessions,
                'errors' => count($errors)
            ]);

            // Return only successful sessions with clean structure
            return $fullSessionData;
        } catch (\Exception $e) {
            Log::error('Failed to fetch full session data', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);

            throw new \Exception('Failed to fetch full session data: ' . $e->getMessage());
        }
    }

    /**
     * Extract session IDs from case data structure
     */
    protected function extractSessionIdsFromCase($caseData)
    {
        $sessionIds = [];

        // Handle different possible session structures in case data
        if (isset($caseData['Sessions']['SessionId'])) {
            $sessions = $caseData['Sessions']['SessionId'];

            // Single session
            if (is_string($sessions)) {
                $sessionIds[] = $sessions;
            }
            // Multiple sessions as array
            elseif (is_array($sessions)) {
                foreach ($sessions as $sessionId) {
                    if (is_string($sessionId)) {
                        $sessionIds[] = $sessionId;
                    }
                }
            }
        }
        // Alternative structure - Sessions as array of objects
        elseif (isset($caseData['Sessions']) && is_array($caseData['Sessions'])) {
            foreach ($caseData['Sessions'] as $session) {
                if (is_array($session) && isset($session['SessionId'])) {
                    $sessionIds[] = $session['SessionId'];
                } elseif (is_string($session)) {
                    $sessionIds[] = $session;
                }
            }
        }

        // Remove duplicates and filter out empty values
        $sessionIds = array_filter(array_unique($sessionIds), function ($id) {
            return !empty($id) && is_string($id);
        });

        return array_values($sessionIds);
    }

    /**
     * Extract clean session data from SOAP GetSession response
     * Only returns successfully retrieved sessions with proper structure
     */
    protected function extractCleanSessionData($soapResponse)
    {
        try {
            // Convert objects to arrays
            if (is_object($soapResponse)) {
                $soapResponse = json_decode(json_encode($soapResponse), true);
            }

            // Check for successful transaction
            if (
                isset($soapResponse['TransactionStatus']['TransactionStatusCode']) &&
                $soapResponse['TransactionStatus']['TransactionStatusCode'] !== 'Success'
            ) {

                Log::info('Session retrieval failed', [
                    'status' => $soapResponse['TransactionStatus']['TransactionStatusCode'] ?? 'unknown',
                    'message' => $soapResponse['TransactionStatus']['Messages']['Message']['MessageDescription'] ?? 'No message'
                ]);
                return null;
            }

            // Extract the actual session data
            if (isset($soapResponse['Session']) && $soapResponse['Session'] !== null) {
                return $soapResponse['Session'];
            }

            // If no proper session structure, return null
            Log::info('No session data found in response structure');
            return null;
        } catch (\Exception $e) {
            Log::error('Error extracting clean session data', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
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
            'full_cases' => [
                'method' => 'SearchCase + GetCase combination',
                'description' => 'First searches for cases using SearchCase, then fetches detailed data for each case using GetCase. This provides richer case information than SearchCase alone.',
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
                ],
                'note' => 'This method performs multiple SOAP calls and may take longer than standard SearchCase'
            ],
            'full_sessions' => [
                'method' => 'SearchCase + GetCase + GetSession combination',
                'description' => 'First searches for cases using SearchCase, then fetches detailed data for each case using GetCase, and finally fetches detailed session data for each session using GetSession. This provides the richest session information available.',
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
                ],
                'note' => 'This method performs the most SOAP calls (SearchCase + GetCase for each case + GetSession for each session) and will take the longest time but provides the most comprehensive session data'
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
     * Get available resource types
     */
    public function getAvailableResources()
    {
        return [
            'clients' => 'Client Data',
            'cases' => 'Case Data',
            'full_cases' => 'Fetch Full Case Data (SearchCase + GetCase)',
            'full_sessions' => 'Fetch Full Session Data (SearchCase + GetCase + GetSession)',
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
     * Submit case data to DSS Data Exchange
     */
    public function submitCaseData($caseData)
    {
        $parameters = [
            'Case' => $this->formatCaseData($caseData)
        ];

        return $this->soapClient->call('AddCase', $parameters);
    }

    /**
     * Submit session data to DSS Data Exchange
     */
    public function submitSessionData($sessionData)
    {
        $parameters = [
            'Session' => $this->formatSessionData($sessionData)
        ];

        return $this->soapClient->call('AddSession', $parameters);
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
