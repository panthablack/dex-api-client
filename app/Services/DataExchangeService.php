<?php

namespace App\Services;

use App\Enums\FilterType;
use App\Enums\ResourceType;
use App\Helpers\ReferenceData;
use App\Http\Controllers\DataExchangeController;
use App\Http\Controllers\DataMigrationController;
use App\Resources\Filters;
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
            'OrganisationId' => config('soap.dss.organisation_id'),
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
            'OrganisationId' => config('soap.dss.organisation_id'),
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
            'OrganisationId' => config('soap.dss.organisation_id')
        ];

        return $this->soapClient->call('GetAvailableServices', $parameters);
    }

    /**
     * Get reference data for country codes, language codes, etc.
     */
    public function getReferenceData($referenceType)
    {
        $parameters = [
            'OrganisationId' => config('soap.dss.organisation_id'),
            'ReferenceDataCode' => $referenceType
        ];

        return $this->soapClient->call('GetReferenceData', $parameters);
    }

    /**
     * Format client data according to DSS specifications
     */
    protected function formatClientData($data)
    {
        // Process birth date first to determine what will actually be sent to API
        $isEstimate = !empty($data['is_birth_date_estimate']);
        $processedBirthDate = $this->formatBirthDate($data['date_of_birth'] ?? null, $isEstimate);

        // Create modified data for SLK generation using the processed birth date
        $dataForSLK = $data;
        if ($processedBirthDate && $isEstimate) {
            // Extract just the date part from the DateTime for SLK calculation
            $dataForSLK['date_of_birth'] = substr($processedBirthDate, 0, 10); // yyyy-mm-dd part only
        }

        $clientData = [
            'ClientId' => $data['client_id'] ?? null,
            'SLK' => $this->generateSLK($dataForSLK), // Use processed data for SLK
            'GivenName' => $data['first_name'] ?? null,
            'FamilyName' => $data['last_name'] ?? null,
            'BirthDate' => $processedBirthDate,
            'IsBirthDateAnEstimate' => $isEstimate,
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
            'ConsentToProvideDetails' => !empty($data['consent_to_provide_details']),
            'ConsentedForFutureContacts' => !empty($data['consent_to_be_contacted']),
            'IsUsingPsuedonym' => !empty($data['is_using_psuedonym']),
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

        // If it's already in the correct format with milliseconds, return as is
        if (preg_match('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}/', $date)) {
            return $date;
        }

        // Convert date to DSS DateTime format with milliseconds
        try {
            $dateObj = $this->parseFlexibleDate($date);
            return $dateObj->format('Y-m-d\TH:i:s.000');
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
     * Map ATSI status to DSS codes based on reference data
     */
    protected function mapATSICode($status)
    {
        // Use the actual DSS reference data codes
        $atsiMap = [
            '2' => 'NO',                // Non indigenous – neither Aboriginal nor Torres Strait Islander origin
            '3' => 'ABORIGINAL',        // Of Aboriginal origin but not Torres Strait Islander
            '4' => 'TSI',               // Of Torres Strait Islander origin but not Aboriginal (Torres Strait Islander)
            '5' => 'BOTH',              // Both Aboriginal and Torres Strait Islander origin
            '9' => 'NOTSTATED'          // No information/Not stated
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

        // Handle missing names according to DSS spec
        if (empty($lastName)) {
            $lastNamePart = '999'; // All 9s for missing name
        } else {
            // Extract letters from last name (2nd, 3rd, 5th positions)
            $lastNamePart = '';
            $lastNamePart .= isset($lastName[1]) ? $lastName[1] : '2'; // 2nd letter or '2' if missing
            $lastNamePart .= isset($lastName[2]) ? $lastName[2] : '2'; // 3rd letter or '2' if missing
            $lastNamePart .= isset($lastName[4]) ? $lastName[4] : '2'; // 5th letter or '2' if missing

            // DSS Rule: "a 2 should always be proceeded by a letter of the alphabet"
            // If first character would be '2', it means lastName has only 1 character, which violates this rule
            if ($lastNamePart[0] === '2') {
                $lastNamePart = '999'; // Use 9s for invalid short name
            }
        }

        if (empty($firstName)) {
            $firstNamePart = '99'; // All 9s for missing name
        } else {
            // Extract letters from first name (2nd, 3rd positions)
            $firstNamePart = '';
            $firstNamePart .= isset($firstName[1]) ? $firstName[1] : '2'; // 2nd letter or '2' if missing
            $firstNamePart .= isset($firstName[2]) ? $firstName[2] : '2'; // 3rd letter or '2' if missing

            // DSS Rule: "a 2 should always be proceeded by a letter of the alphabet"
            // If first character would be '2', it means firstName has only 1 character, which violates this rule
            if ($firstNamePart[0] === '2') {
                $firstNamePart = '99'; // Use 9s for invalid short name
            }
        }

        // Format date as ddmmyyyy (DSS specification format)
        $dob = $data['date_of_birth'] ?? '';
        if ($dob) {
            try {
                $datePart = $this->formatDateForSLK($dob);
            } catch (\Exception $e) {
                $datePart = '01011900'; // Default if parsing fails
            }
        } else {
            $datePart = '01011900'; // Default if no date provided
        }

        // Map gender to SLK codes according to DSS spec:
        // Code 1 for Man or male, Code 2 for Woman or female, Code 3 for Non-binary, Code 3 for [I/They] use different term, Code 9 for Not stated
        $gender = strtoupper($data['gender'] ?? '');
        $genderCode = match ($gender) {
            'M', 'MALE', 'MAN' => '1',
            'F', 'FEMALE', 'WOMAN' => '2',
            'X', 'NONBINARY', 'NON-BINARY', 'OTHER' => '3',
            default => '9'
        };

        $slk = $lastNamePart . $firstNamePart . $datePart . $genderCode;

        // Validate SLK against DSS regex pattern
        if (!$this->validateSLK($slk)) {
            Log::warning('Generated SLK failed validation', [
                'slk' => $slk,
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'date_of_birth' => $data['date_of_birth'] ?? '',
                'gender' => $data['gender'] ?? ''
            ]);
        }

        return $slk;
    }

    /**
     * Format date for SLK generation, handling Australian dd/mm/yyyy format
     */
    protected function formatDateForSLK($dateString)
    {
        // Handle different date formats correctly
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateString, $matches)) {
            // dd/mm/yyyy format (Australian format)
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            return $day . $month . $year; // ddmmyyyy
        } elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $dateString, $matches)) {
            // yyyy-mm-dd format (ISO format)
            $year = $matches[1];
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
            return $day . $month . $year; // ddmmyyyy
        } else {
            // Try to parse with DateTime as fallback
            $dateObj = new \DateTime($dateString);
            return $dateObj->format('dmY'); // ddmmyyyy format
        }
    }

    /**
     * Validate SLK against DSS regular expression pattern
     */
    protected function validateSLK($slk)
    {
        // DSS SLK regular expression from specification
        $pattern = '/^([9]{3}|[A-Z]([2]{2}|[A-Z][A-Z,2]))([9]{2}|[A-Z][A-Z,2])(((((0[1-9]|[1-2][0-9]))|(3[01]))((0[13578])|(1[02])))|((((0[1-9]|[1-2][0-9]))|(30))((0[469])|(11)))|((0[1-9]|[1-2][0-9])02))(19|2[0-9])[0-9]{2}[1239]$/';

        return preg_match($pattern, $slk) === 1;
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
        // Try to get from reference data first
        $validCountries = $this->getValidCountryCodes();

        if ($validCountries && isset($validCountries[$country])) {
            return $validCountries[$country];
        }

        // Fallback to hardcoded values if reference data is unavailable
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
        // Try to get from reference data first
        $validLanguages = $this->getValidLanguageCodes();

        if ($validLanguages && isset($validLanguages[$language])) {
            return $validLanguages[$language];
        }

        // Fallback to hardcoded values if reference data is unavailable
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
     * Format birth date according to DSS specification
     * - If IsBirthDateAnEstimate is true: format as 'yyyy-01-01T00:00:00.000'
     * - If IsBirthDateAnEstimate is false: format as 'yyyy-mm-ddT00:00:00.000' (real birth date)
     * Note: DSS API requires DateTime format, not just date
     */
    protected function formatBirthDate($date, $isEstimate = false)
    {
        if (empty($date)) {
            return null;
        }

        if ($isEstimate) {
            // DSS Spec: If IsBirthDateAnEstimate is true, use yyyy-01-01 but with DateTime format
            try {
                // Parse the date to extract the year, but always use January 1st
                $dateObj = $this->parseFlexibleDate($date);
                $year = $dateObj->format('Y');
                return $year . '-01-01T00:00:00.000';
            } catch (\Exception $e) {
                // If date parsing fails, use a reasonable default year
                return '1990-01-01T00:00:00.000';
            }
        }

        // DSS Spec: If IsBirthDateAnEstimate is false, use real birth date with DateTime format
        try {
            $dateObj = $this->parseFlexibleDate($date);
            return $dateObj->format('Y-m-d\T00:00:00.000');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse dates in various formats (dd/mm/yyyy, yyyy-mm-dd, etc.)
     */
    protected function parseFlexibleDate($dateString)
    {
        // Handle dd/mm/yyyy format (Australian format)
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateString, $matches)) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];
            return new \DateTime("{$year}-{$month}-{$day}");
        }

        // Handle yyyy-mm-dd or other formats via DateTime
        return new \DateTime($dateString);
    }

    /**
     * Format case data according to DSS AddCase specifications
     */
    protected function formatCaseData($data)
    {
        $caseData = [
            'CaseId' => $data['case_id'] ?? null,
            'OutletActivityId' => (int)($data['outlet_activity_id'] ?? 61936), // Default to first outlet activity
        ];

        // Optional fields
        if (!empty($data['total_unidentified_clients'])) {
            $caseData['TotalNumberOfUnidentifiedClients'] = (int)$data['total_unidentified_clients'];
        }

        if (!empty($data['client_attendance_profile_code'])) {
            $caseData['ClientAttendanceProfileCode'] = $data['client_attendance_profile_code'];
        }

        if (!empty($data['end_date'])) {
            $caseData['EndDate'] = $this->formatDate($data['end_date']);
        }

        if (!empty($data['ag_business_type_code'])) {
            $caseData['AgBusinessTypeCode'] = $data['ag_business_type_code'];
        }

        // Complex optional structures
        if (!empty($data['parenting_agreement_outcome'])) {
            $caseData['ParentingAgreementOutcome'] = [
                'ParentingAgreementOutcomeCode' => $data['parenting_agreement_outcome']['outcome_code'] ?? null,
                'DateOfParentingAgreement' => $this->formatDate($data['parenting_agreement_outcome']['date'] ?? null),
                'DidLegalPractitionerAssistWithFormalisingAgreement' => !empty($data['parenting_agreement_outcome']['legal_practitioner_assist'])
            ];
        }

        if (!empty($data['section_60i'])) {
            $caseData['Section60I'] = [
                'Section60ICertificateTypeCode' => $data['section_60i']['certificate_type_code'] ?? null,
                'DateIssued' => $this->formatDate($data['section_60i']['date_issued'] ?? null)
            ];
        }

        if (!empty($data['property_agreement_outcome'])) {
            $caseData['PropertyAgreementOutcome'] = [
                'PropertyAgreementOutcomeCode' => $data['property_agreement_outcome']['outcome_code'] ?? null,
                'DateOfPropertyAgreement' => $this->formatDate($data['property_agreement_outcome']['date'] ?? null),
                'DidLegalPractitionerAssistInPropertyMediation' => !empty($data['property_agreement_outcome']['legal_practitioner_assist'])
            ];
        }

        return $caseData;
    }

    /**
     * Format session data according to DSS specifications
     */
    protected function formatSessionData($data)
    {
        $formatted = [
            'SessionId' => $data['session_id'] ?? null,
            'SessionDate' => $this->formatDate($data['session_date'] ?? null),
        ];

        // Mandatory fields
        if (isset($data['service_type_id']) && is_numeric($data['service_type_id'])) {
            $formatted['ServiceTypeId'] = (int) $data['service_type_id'];
        } else {
            $formatted['ServiceTypeId'] = 5;
        }

        if (isset($data['fees_charged'])) {
            $formatted['TotalNumberOfUnidentifiedClients'] = (int) $data['total_number_of_unidentified_clients'];
        } else {
            // Must always be set when no clients are passed so defaulting to 1
            $formatted['TotalNumberOfUnidentifiedClients'] = 1;
        }

        // Optional fields
        if (isset($data['fees_charged'])) {
            $formatted['FeesCharged'] = (float) $data['fees_charged'];
        }

        if (isset($data['money_business_community_education_workshop_code'])) {
            $formatted['MoneyBusinessCommunityEducationWorkshopCode'] = $data['money_business_community_education_workshop_code'];
        }

        if (isset($data['interpreter_present'])) {
            $formatted['InterpreterPresent'] = (bool) $data['interpreter_present'];
        }

        if (isset($data['extra_items']) && is_array($data['extra_items'])) {
            $formatted['ExtraItems'] = [];
            foreach ($data['extra_items'] as $item) {
                if (isset($item['extra_item_code'])) {
                    $formatted['ExtraItems'][] = ['ExtraItemCode' => $item['extra_item_code']];
                }
            }
        }

        if (isset($data['quantity'])) {
            $formatted['Quantity'] = (int) $data['quantity'];
        }

        // Handle both 'time' (DSS field) and 'duration_minutes' (form field)
        // This doesn't work with the activity type we know works at the moment
        // if (isset($data['time'])) {
        //     $formatted['Time'] = (int) $data['time'];
        // } elseif (isset($data['duration_minutes'])) {
        //     $formatted['Time'] = (int) $data['duration_minutes'];
        // }

        if (isset($data['total_cost'])) {
            $formatted['TotalCost'] = (int) $data['total_cost'];
        }

        if (isset($data['topic_code'])) {
            $formatted['TopicCode'] = $data['topic_code'];
        }

        if (isset($data['service_setting_code'])) {
            $formatted['ServiceSettingCode'] = $data['service_setting_code'];
        }

        if (isset($data['hardship_type_code'])) {
            $formatted['HardshipTypeCode'] = $data['hardship_type_code'];
        }

        if (isset($data['external_referral_destination_code'])) {
            $formatted['ExternalReferralDestinationCode'] = $data['external_referral_destination_code'];
        }

        // Clients array
        if (isset($data['clients']) && is_array($data['clients'])) {
            $formatted['Clients'] = [];
            foreach ($data['clients'] as $client) {
                $sessionClient = [
                    'ClientId' => $client['client_id'] ?? null,
                    'ParticipationCode' => $client['participation_code'] ?? null,
                ];

                // Client referral out with purpose
                if (isset($client['client_referral_out_with_purpose']) && is_array($client['client_referral_out_with_purpose'])) {
                    $sessionClient['ClientReferralOutWithPurpose'] = [];
                    foreach ($client['client_referral_out_with_purpose'] as $referral) {
                        $referralFormatted = [
                            'TypeCode' => $referral['type_code'] ?? null,
                        ];

                        if (isset($referral['purpose_codes']) && is_array($referral['purpose_codes'])) {
                            $referralFormatted['PurposeCodes'] = [];
                            foreach ($referral['purpose_codes'] as $purposeCode) {
                                $referralFormatted['PurposeCodes'][] = ['PurposeCode' => $purposeCode];
                            }
                        }

                        $sessionClient['ClientReferralOutWithPurpose'][] = ['Referral' => $referralFormatted];
                    }
                }

                $formatted['Clients'][] = ['SessionClient' => $sessionClient];
            }
        }

        return $formatted;
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
        if (env('DETAILED_LOGGING'))
            Log::info('Starting bulk AddCase submission', [
                'total_cases' => count($caseDataArray),
                'case_ids' => array_map(function ($case) {
                    return $case['case_id'] ?? 'unknown';
                }, $caseDataArray)
            ]);

        $results = [];
        $successCount = 0;
        $errorCount = 0;

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
                    $errorCount++;
                } else {
                    $results[$index] = [
                        'status' => 'success',
                        'result' => $result,
                        'case_data' => $caseData
                    ];
                    $successCount++;
                }
            } catch (\Exception $e) {
                $results[$index] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'case_data' => $caseData
                ];
                $errorCount++;
            }
        }

        if (env('DETAILED_LOGGING'))
            Log::info('Bulk AddCase submission completed', [
                'total_cases' => count($caseDataArray),
                'successful_cases' => $successCount,
                'failed_cases' => $errorCount,
                'success_rate' => count($caseDataArray) > 0 ? round(($successCount / count($caseDataArray)) * 100, 2) . '%' : '0%'
            ]);

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
     * Generate sample client data for testing using Laravel's fake() helper
     */
    public function generateSampleClientData()
    {
        $fake = fake();

        // Generate basic data first - ensure names are long enough for SLK generation
        $firstName = $fake->firstName();
        $lastName = $fake->lastName();

        // Ensure names are at least 5 characters for proper SLK generation (need 2nd, 3rd, 5th letters)
        while (strlen($firstName) < 3) {
            $firstName = $fake->firstName();
        }
        while (strlen($lastName) < 5) {
            $lastName = $fake->lastName();
        }

        $dateOfBirth = $fake->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d');
        $isBirthDateEstimate = $fake->boolean(20); // 20% chance of being estimate
        $gender = $fake->randomElement(['M', 'F']);

        // Use only confirmed working addresses from existing successful client data
        $locations = [
            ['suburb' => 'Sydney', 'state' => 'NSW', 'postcode' => '2000'],
            ['suburb' => 'Melbourne', 'state' => 'VIC', 'postcode' => '3000'],
            ['suburb' => 'Brisbane', 'state' => 'QLD', 'postcode' => '4001'],
            ['suburb' => 'Perth', 'state' => 'WA', 'postcode' => '6000'],
            ['suburb' => 'Adelaide', 'state' => 'SA', 'postcode' => '5000'],
            ['suburb' => 'Hobart', 'state' => 'TAS', 'postcode' => '7000'],
            ['suburb' => 'Richmond', 'state' => 'VIC', 'postcode' => '3121'],
            ['suburb' => 'Fortitude Valley', 'state' => 'QLD', 'postcode' => '4006'],
            ['suburb' => 'Fremantle', 'state' => 'WA', 'postcode' => '6160'],
            ['suburb' => 'Launceston', 'state' => 'TAS', 'postcode' => '7250']
        ];

        $location = $fake->randomElement($locations);

        // Create the base client data
        $clientData = [
            'client_id' => ReferenceData::generateResourceId('CLIENT'),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'date_of_birth' => $dateOfBirth,
            'is_birth_date_estimate' => $isBirthDateEstimate,
            'gender' => $gender,
            'country_of_birth' => $this->getSafeCountryForFakeData(), // Use reference data for valid country
            'suburb' => $location['suburb'],
            'state' => $location['state'],
            'postal_code' => $location['postcode'],
            'address_line1' => $fake->streetAddress(),
            'address_line2' => $fake->boolean(30) ? $fake->secondaryAddress() : null,
            'primary_language' => $this->getSafeLanguageForFakeData(), // Use reference data for valid language
            'indigenous_status' => $this->getSafeATSIForFakeData(), // Use reference data for valid ATSI status
            'interpreter_required' => $fake->boolean(10), // 10% chance
            'disability_flag' => false, // Disable for now to avoid complications
            'consent_to_provide_details' => true, // Always true for valid submissions
            'consent_to_be_contacted' => true, // Always true to avoid validation issues
            'is_using_psuedonym' => false // Always false for simplicity
        ];

        return $clientData;
    }

    /**
     * Generate sample case data for testing using Laravel's fake() helper
     */
    public function generateSampleCaseData($clientId = null)
    {
        $fake = fake();

        // Available outlet activity IDs from your system
        // $outletActivityIds = [61932, 61936, 61933, 61937, 61935, 61934];
        // This is the only activity type we know works at the moment.
        $outletActivityIds = [61936];

        // Use provided client ID, or get a real one from the system, or fallback to fake
        $selectedClientId = $clientId;
        if (!$selectedClientId) {
            $existingClientIds = $this->getExistingClientIds();
            if (!empty($existingClientIds)) {
                $selectedClientId = $fake->randomElement($existingClientIds);
            } else {
                // Fallback to fake client ID if no real ones available
                $selectedClientId = ReferenceData::generateResourceId('CLIENT');
            }
        }

        $caseData = [
            'case_id' => ReferenceData::generateResourceId('CASE'),
            'client_id' => $selectedClientId,
            'outlet_activity_id' => $fake->randomElement($outletActivityIds),
            'referral_source_code' => $this->getSafeReferralSourceForFakeData(),
            // We don't know when this is needed so I'm making it always set to be a random int
            // between 1 and 20
            'total_unidentified_clients' => $fake->numberBetween(1, 20),
            'client_attendance_profile_code' => $this->getSafeAttendanceProfileForFakeData(),
            'end_date' => $fake->boolean(40) ? $fake->dateTimeBetween('-60 days', 'yesterday')->format('Y-m-d') : null,
            'exit_reason_code' => $fake->boolean(30) ? $this->getSafeExitReasonForFakeData() : null,
        ];

        // Add client information for the case
        $caseData['clients'] = [[
            'client_id' => $caseData['client_id'],
            'reasons_for_assistance' => [
                [
                    'assistance_needed_code' => $fake->randomElement(['PHYSICAL', 'EMOTIONAL', 'FINANCIAL', 'HOUSING', 'LEGAL']),
                    'is_primary' => true
                ]
            ],
            'referral_source_code' => $caseData['referral_source_code'],
            'exit_reason_code' => $caseData['exit_reason_code']
        ]];

        // Occasionally add complex optional structures for testing
        if ($fake->boolean(20)) {
            $caseData['parenting_agreement_outcome'] = [
                'outcome_code' => $fake->randomElement(['FULL', 'PARTIAL', 'NONE']),
                'date' => $fake->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
                'legal_practitioner_assist' => $fake->boolean()
            ];
        }

        if ($fake->boolean(10)) {
            $caseData['section_60i'] = [
                'certificate_type_code' => $fake->randomElement(['GENUINE', 'EXEMPTION']),
                'date_issued' => $fake->dateTimeBetween('-1 year', 'now')->format('Y-m-d')
            ];
        }

        if ($fake->boolean(15)) {
            $caseData['property_agreement_outcome'] = [
                'outcome_code' => $fake->randomElement(['FULL', 'PARTIAL', 'NONE']),
                'date' => $fake->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
                'legal_practitioner_assist' => $fake->boolean()
            ];
        }

        return $caseData;
    }

    /**
     * Returns a random service type
     */
    public function getRandomServiceType()
    {
        $serviceTypes = ReferenceData::serviceTypes();
        return $serviceTypes[random_int(1, count($serviceTypes) - 1)];
    }

    /**
     * Generate sample session data for testing using Laravel's fake() helper
     */
    public function generateSampleSessionData($caseId = null)
    {
        $fake = fake();

        // Use provided case ID, or get a real one from the system, or fallback to fake
        $selectedCaseId = $caseId;
        if (!$selectedCaseId) {
            $existingCaseIds = $this->getExistingCaseIds();
            if (!empty($existingCaseIds)) {
                $selectedCaseId = $fake->randomElement($existingCaseIds);
            } else {
                // Fallback to fake case ID if no real ones available
                $selectedCaseId = ReferenceData::generateResourceId('CASE');
            }
        }

        $sessionData = [
            'session_id' => ReferenceData::generateResourceId('SESSION'),
            'case_id' => $selectedCaseId,
            'session_date' => $fake->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
            // Get valid service type and use both ID and name
            'service_type_id' => 5, // Hardcoded to Counselling as requested
            'duration_minutes' => $fake->randomElement([30, 45, 60, 90, 120]),
            'location' => $fake->randomElement([
                'Office Room 1',
                'Office Room 2',
                'Community Center',
                'Client Home',
                'Phone/Video Call',
                'Public Space'
            ]),
            'session_status' => $fake->randomElement(['Scheduled', 'Completed', 'Cancelled', 'No Show']),
            'notes' => $fake->paragraph(1),
            'attendees' => $fake->randomElement([
                'Client, Counsellor',
                'Client, Family, Social Worker',
                'Client, Case Manager',
                'Client, Therapist, Psychiatrist'
            ]),
            'outcome' => $fake->randomElement([
                'Ongoing',
                'Goals Met',
                'Referred',
                'Discontinued',
                'Needs Review',
                'Emergency Response'
            ])
        ];

        // Always add this in as it will cause issues if clients aren't added
        $sessionData['total_number_of_unidentified_clients'] = $fake->numberBetween(1, 5);

        // Optional DSS fields (added randomly)

        if ($fake->boolean(60)) {
            $sessionData['fees_charged'] = $fake->randomFloat(2, 0, 1000);
        }

        if ($fake->boolean(30)) {
            $sessionData['money_business_community_education_workshop_code'] = 'WRK0' . $fake->numberBetween(1, 9);
        }

        if ($fake->boolean(40)) {
            $sessionData['interpreter_present'] = $fake->boolean();
        }

        if ($fake->boolean(50)) {
            $sessionData['extra_items'] = [
                ['extra_item_code' => $fake->randomElement(['KITCHEN', 'TRANSPORT', 'CHILDCARE', 'MATERIALS'])]
            ];
        }

        if ($fake->boolean(80)) {
            $sessionData['quantity'] = $fake->numberBetween(1, 10);
        }

        // This doesn't work with the activity type we know works at the moment
        // if ($fake->boolean(80)) {
        //     $sessionData['time'] = $fake->randomElement([30, 45, 60, 90, 120]);
        // }

        if ($fake->boolean(60)) {
            $sessionData['total_cost'] = $fake->numberBetween(50, 500);
        }

        if ($fake->boolean(70)) {
            $sessionData['topic_code'] = $fake->randomElement(['ABUSENEGLECT', 'FINANCIAL', 'LEGAL', 'FAMILY', 'HOUSING']);
        }

        if ($fake->boolean(70)) {
            $sessionData['service_setting_code'] = $fake->randomElement(['COMMVENUE', 'OFFICE', 'HOME', 'PHONE', 'VIDEO']);
        }

        if ($fake->boolean(40)) {
            $sessionData['hardship_type_code'] = $fake->randomElement(['DROUGHT', 'FLOOD', 'FIRE', 'ECONOMIC', 'HEALTH']);
        }

        if ($fake->boolean(30)) {
            $sessionData['external_referral_destination_code'] = $fake->randomElement(['LEGAL', 'MEDICAL', 'FINANCIAL', 'HOUSING']);
        }

        // Generate clients for the session
        if ($fake->boolean(80)) {
            $sessionData['clients'] = [];
            $clientCount = $fake->numberBetween(1, 3);
            for ($i = 0; $i < $clientCount; $i++) {
                $client = [
                    'client_id' => 'CL' . str_pad($fake->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
                    'participation_code' => $fake->randomElement(['Client', 'Support Person', 'Advocate', 'Interpreter']),
                ];

                // Add referrals sometimes
                if ($fake->boolean(40)) {
                    $client['client_referral_out_with_purpose'] = [
                        [
                            'type_code' => $fake->randomElement(['Internal', 'External']),
                            'purpose_codes' => [
                                $fake->randomElement(['PERSONAL', 'FINANCIAL', 'LEGAL', 'HOUSING', 'HEALTH'])
                            ]
                        ]
                    ];
                }

                $sessionData['clients'][] = $client;
            }
        }

        return $sessionData;
    }

    /**
     * Retrieve client data from DSS Data Exchange
     */
    public function getClientData(Filters $filters)
    {
        $criteria = $this->formatSearchCriteria($filters, ResourceType::CLIENT);

        $parameters = [
            'Criteria' => $criteria
        ];

        // Log the exact parameters being sent
        if (env('DETAILED_LOGGING'))
            Log::info('SearchClient Request Parameters:', [
                'filters_received' => $filters,
                'formatted_criteria' => $criteria,
                'full_parameters' => $parameters
            ]);

        return $this->soapClient->call('SearchClient', $parameters);
    }

    /**
     * Get client data with pagination metadata - for controllers
     */
    public function getClientDataWithPagination(Filters $filters)
    {
        $result = $this->getClientData($filters);
        return $this->addPaginationMetadata($result, $filters);
    }

    /**
     * Add pagination metadata to DSS API response
     */
    protected function addPaginationMetadata($result, Filters $filters)
    {
        // Convert result to array for consistent handling
        if (is_object($result)) {
            $resultArray = json_decode(json_encode($result), true);
        } else {
            $resultArray = $result;
        }

        // Extract pagination info from DSS response
        $totalCount = $resultArray['TotalCount'] ?? null;
        $currentPage = $filters->get('page_index') ?? 1;
        $perPage = $filters->get('page_size') ?? config('features.pagination.default_page_size', 10);

        // Calculate pagination details
        $lastPage = $totalCount ? max(1, intval(ceil($totalCount / $perPage))) : 1;
        $from = $totalCount > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
        $to = min($currentPage * $perPage, $totalCount ?? 0);

        // Create pagination metadata
        $pagination = [
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $totalCount,
            'from' => $from,
            'to' => $to,
            'has_more_pages' => $currentPage < $lastPage,
            'prev_page_url' => $currentPage > 1 ? '?page=' . ($currentPage - 1) : null,
            'next_page_url' => $currentPage < $lastPage ? '?page=' . ($currentPage + 1) : null,
        ];

        // Add pagination to the result
        if (is_array($result)) {
            $result['pagination'] = $pagination;
        } else {
            $result->pagination = (object) $pagination;
        }

        return $result;
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
        if (env('DETAILED_LOGGING'))
            Log::info('GetClient Request Parameters:', [
                'client_id_received' => $clientId,
                'full_parameters' => $parameters
            ]);

        return $this->soapClient->call('GetClient', $parameters);
    }

    /**
     * Retrieve case data from DSS Data Exchange
     */
    public function getCaseData(Filters $filters)
    {
        $criteria = $this->formatSearchCriteria($filters, ResourceType::CASE);

        $parameters = [
            'Criteria' => $criteria
        ];

        // Log the exact parameters being sent
        if (env('DETAILED_LOGGING'))
            Log::info('SearchClient Request Parameters:', [
                'filters_received' => $filters,
                'formatted_criteria' => $criteria,
                'full_parameters' => $parameters
            ]);

        return $this->soapClient->call('SearchCase', $parameters);
    }

    /**
     * Get case data with pagination metadata - for controllers
     */
    public function getCaseDataWithPagination(Filters $filters)
    {
        $result = $this->getCaseData($filters);
        return $this->addPaginationMetadata($result, $filters);
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
        if (env('DETAILED_LOGGING'))
            Log::info('GetCase Request Parameters:', [
                'case_id_received' => $caseId,
                'full_parameters' => $parameters
            ]);

        return $this->soapClient->call('GetCase', $parameters);
    }

    public function enrichCaseData($caseData, $attempt = 1): array | null
    {
        try {
            $caseId = null;

            // Extract Case ID
            if (is_array($caseData) && isset($caseData['CaseId'])) {
                $caseId = $caseData['CaseId'];
            } elseif (is_array($caseData) && isset($caseData['CaseDetail']['CaseId'])) {
                $caseId = $caseData['CaseDetail']['CaseId'];
            }

            // Skip invalid data - only process actual case IDs
            if (!$caseId) {
                if (env('DETAILED_LOGGING'))
                    Log::info('Skipping case with missing ID', [
                        'case_info_type' => gettype($caseData)
                    ]);
                throw new \Exception('missing caseId');
            }

            if (env('DETAILED_LOGGING'))
                Log::info('Fetching full data for case', ['case_id' => $caseId]);

            // Get detailed case data using GetCase
            if ($caseId === 'CASE_2606') throw new \Exception('Burn baby!');
            if ($caseId === 'CASE_7722') throw new \Exception('Burn baby!');
            $fullCaseResult = $this->getCaseById($caseId);

            // Extract clean case data from the SOAP response
            return $this->extractCleanCaseData($fullCaseResult) ?? null;
        } catch (\Exception $e) {
            if ($attempt < 3) {
                Log::error('Failed to get full data for case', [
                    'case_id' => $caseId,
                    'error' => $e->getMessage()
                ]);
                Log::error("Attempt $attempt - Trying again.");
                return $this->enrichCaseData($caseData, $attempt + 1);
            } else {
                Log::error('Failed to get full data for case', [
                    'case_id' => $caseId,
                    'error' => $e->getMessage()
                ]);
                Log::error("Attempt $attempt - Reached Limit, marking as failed.");
                throw $e;
            }
        }
    }

    /**
     * Fetch Full Case Data - Search for cases then get detailed data for each using GetCase
     * This provides richer case information compared to SearchCase alone
     * Returns the same structure as getCaseDataWithPagination but with enriched case data
     */
    public function fetchFullCaseData(Filters $filters)
    {
        try {
            // Use SearchCase strategy to get all cases
            if (env('DETAILED_LOGGING'))
                Log::info('Fetching full case data - starting with SearchCase strategy', [
                    'filters' => $filters
                ]);

            $searchResult = $this->getCaseData($filters);

            // Add pagination metadata to the result
            $searchResult = $this->addPaginationMetadata($searchResult, $filters);

            // Convert objects to arrays for consistent handling
            if (is_object($searchResult)) {
                $searchResult = json_decode(json_encode($searchResult), true);
            }

            // Extract cases from the search result to enrich with detailed data
            $cases = [];
            if (isset($searchResult['Cases']['Case'])) {
                $cases = $searchResult['Cases']['Case'];
                // Ensure it's an array (single case comes as object)
                if (!is_array($cases) || (is_array($cases) && isset($cases['CaseId']))) {
                    $cases = [$cases];
                }
            }

            if (empty($cases)) {
                if (env('DETAILED_LOGGING'))
                    Log::info('No cases found in search result');
                // Add pagination metadata to empty result and return
                return $this->addPaginationMetadata($searchResult, $filters);
            }

            if (env('DETAILED_LOGGING'))
                Log::info('Found cases, now fetching full data', [
                    'cases_count' => count($cases),
                    'first_case_id' => isset($cases[0]['CaseId']) ? $cases[0]['CaseId'] : 'unknown'
                ]);

            // Now get detailed data for each case using GetCase
            $enrichedCases = [];
            $errorsCount = 0;
            $successCount = 0;

            foreach ($cases as $caseInfo) {
                try {
                    $enrichedCase = $this->enrichCaseData($caseInfo);
                    if ($enrichedCase) {
                        $enrichedCases[] = $enrichedCase;
                        $successCount++;
                    } else {
                        $errorsCount++;
                        Log::error('Failed to enrich case: ', $caseInfo);
                        throw new \Exception("Failed to enrich case: $caseInfo");
                    }
                } catch (\Throwable $th) {
                    $errorsCount++;
                    Log::error('Failed to fetch full case data: ', $caseInfo);
                    throw new \Exception('Failed to fetch full case data: ' . $th->getMessage());
                }
            }

            if (env('DETAILED_LOGGING'))
                Log::info('Fetch full case data completed', [
                    'total_cases_found' => count($cases),
                    'successful_fetches' => $successCount,
                    'errors' => $errorsCount
                ]);

            // Replace the cases in the original search result with enriched data
            $enrichedCases = [
                'Cases' => [
                    'Case' => $enrichedCases
                ]
            ];

            // Add pagination metadata and return both cases arrays
            return $this->addPaginationMetadata($enrichedCases, $filters);
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
                Log::error('Case retrieval failed', [
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
            if (env('DETAILED_LOGGING'))
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
    public function fetchFullSessionData(Filters $filters)
    {
        try {
            // First, get all full case data
            if (env('DETAILED_LOGGING'))
                Log::info('Fetching full session data - starting with fetchFullCaseData', [
                    'filters' => $filters
                ]);

            $fullCaseResult = $this->fetchFullCaseData($filters);

            // Extract cases from the new pagination structure
            $fullCaseData = [];
            if (isset($fullCaseResult['Cases']['Case'])) {
                $fullCaseData = $fullCaseResult['Cases']['Case'];
                // Ensure it's an array (single case comes as object)
                if (!is_array($fullCaseData) || (is_array($fullCaseData) && isset($fullCaseData['CaseId']))) {
                    $fullCaseData = [$fullCaseData];
                }
            }

            if (empty($fullCaseData)) {
                if (env('DETAILED_LOGGING'))
                    Log::info('No cases found, returning empty session array');
                return [];
            }

            if (env('DETAILED_LOGGING'))
                Log::info('Found cases, now extracting sessions', [
                    'cases_count' => count($fullCaseData)
                ]);

            // Extract all sessions from all cases and fetch detailed session data
            $fullSessionData = [];
            $totalSessions = 0;
            $successfulSessions = 0;
            $errors = [];

            foreach ($fullCaseData as $caseData) {
                $caseId = $caseData['CaseDetail']['CaseId'] ?? $caseData['CaseId'] ?? null;

                if (!$caseId) {
                    Log::warning('Case without CaseId found', ['case_data_keys' => array_keys($caseData)]);
                    continue;
                }

                // Extract session IDs from the case data
                $sessionIds = $this->extractSessionIdsFromCase($caseData);

                if (empty($sessionIds)) {
                    if (env('DETAILED_LOGGING'))
                        Log::info('No sessions found in case', ['case_id' => $caseId]);
                    continue;
                }

                if (env('DETAILED_LOGGING'))
                    Log::info('Processing sessions for case', [
                        'case_id' => $caseId,
                        'session_count' => count($sessionIds)
                    ]);

                foreach ($sessionIds as $sessionId) {
                    $totalSessions++;

                    try {
                        if (env('DETAILED_LOGGING'))
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

            if (env('DETAILED_LOGGING'))
                Log::info('Fetch full session data completed', [
                    'cases_processed' => count($fullCaseData),
                    'total_sessions_found' => $totalSessions,
                    'successful_sessions' => $successfulSessions,
                    'errors' => count($errors)
                ]);

            // Return successful sessions (maintain backward compatibility by returning simple array)
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
     * Fetch Session Data for a specific case - Uses getCaseById then fetches detailed session data
     * This provides session information for a specific case by using GetCase + GetSession calls
     *
     * @param string $caseId The case ID to fetch sessions for
     * @return array Array of session data
     */
    public function fetchSessionData($caseId)
    {
        try {
            if (env('DETAILED_LOGGING'))
                Log::info('Fetching session data for specific case', [
                    'case_id' => $caseId
                ]);

            // Get the case data using getCaseById
            $caseResult = $this->getCaseById($caseId);

            // Convert objects to arrays for consistent handling
            if (is_object($caseResult)) {
                $caseResult = json_decode(json_encode($caseResult), true);
            }

            // Extract case data from the response
            $caseData = null;
            if (isset($caseResult['Result']['Case'])) {
                $caseData = $caseResult['Result']['Case'];
            } elseif (isset($caseResult['Case'])) {
                $caseData = $caseResult['Case'];
            }

            if (!$caseData) {
                Log::warning('No case data found for case ID', ['case_id' => $caseId]);
                return [];
            }

            // Extract session IDs from the case data
            $sessionIds = $this->extractSessionIdsFromCase($caseData);

            if (empty($sessionIds)) {
                if (env('DETAILED_LOGGING'))
                    Log::info('No sessions found in case', ['case_id' => $caseId]);
                return [];
            }

            if (env('DETAILED_LOGGING'))
                Log::info('Processing sessions for case', [
                    'case_id' => $caseId,
                    'session_count' => count($sessionIds)
                ]);

            $sessionData = [];
            $successfulSessions = 0;
            $errors = [];

            // Fetch detailed data for each session
            foreach ($sessionIds as $sessionId) {
                try {
                    if (env('DETAILED_LOGGING'))
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

                        $sessionData[] = $cleanSessionData;
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

            if (env('DETAILED_LOGGING'))
                Log::info('Fetch session data completed', [
                    'case_id' => $caseId,
                    'total_sessions_found' => count($sessionIds),
                    'successful_sessions' => $successfulSessions,
                    'errors' => count($errors)
                ]);

            return $sessionData;
        } catch (\Exception $e) {
            Log::error('Failed to fetch session data for case', [
                'case_id' => $caseId,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to fetch session data for case: ' . $e->getMessage());
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
                if (env('DETAILED_LOGGING'))
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
            if (env('DETAILED_LOGGING'))
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
    public function getSessionData(Filters $filters)
    {
        // Debug logging
        if (env('DETAILED_LOGGING'))
            Log::info('getSessionData called with filters:', [
                'filters' => $filters,
                'case_id_present' => isset($filters['case_id']),
                'case_id_value' => $filters['case_id'] ?? 'not set',
                'case_id_empty' => empty($filters['case_id'])
            ]);

        // Check if a Case ID is provided - this is required for session retrieval
        if (!empty($filters['case_id'])) {
            if (env('DETAILED_LOGGING'))
                Log::info('Getting sessions for specific case: ' . $filters['case_id']);
            // Use SearchCase to get the case data, which may include session information
            $caseFilters = new Filters(['case_id' => $filters['case_id']]);
            $caseResult = $this->fetchFullCaseData($caseFilters);

            // Check if the case result contains session data
            // Convert to array if it's an object for consistent handling
            if (is_object($caseResult)) {
                $caseResult = json_decode(json_encode($caseResult), true);
            }

            // Extract cases from the new pagination structure
            $cases = [];
            if (isset($caseResult['Cases']['Case'])) {
                $cases = $caseResult['Cases']['Case'];
                // Ensure it's an array (single case comes as object)
                if (!is_array($cases) || (is_array($cases) && isset($cases['CaseId']))) {
                    $cases = [$cases];
                }
            }

            $sessions = $this->getSessionsFromCases($cases);

            if (!empty($sessions)) {
                if (env('DETAILED_LOGGING'))
                    Log::info('Found session data in case result');
                return $sessions;
            }

            // If no session data found in case, return informative message
            if (env('DETAILED_LOGGING'))
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
     * Get sessions from a case
     */
    public function getSessionsFromCase($case): array
    {
        // Check if Sessions key exists
        if (!isset($case['Sessions'])) {
            if (env('DETAILED_LOGGING'))
                Log::debug('getSessionsFromCase - no Sessions key found in case:', [
                    'case_keys' => is_array($case) ? array_keys($case) : 'not_array',
                    'case_sample' => array_slice($case, 0, 3) // Only show first 3 keys to avoid large logs
                ]);
            return [];
        }

        $caseSessionId = $case['Sessions']['SessionId'];
        if (!($caseSessionId ?? null)) {
            Log::error('getSessionsFromCase failing - case structure:', [
                'case_keys' => is_array($case) ? array_keys($case) : 'not_array',
                'has_sessions' => isset($case['Sessions']),
                'sessions_structure' => $case['Sessions'] ?? 'null',
                'case_sample' => array_slice($case, 0, 3) // Only show first 3 keys to avoid large logs
            ]);
            throw new \Exception('Cannot get Sessions from a Case without a Case.');
        }

        $sessionIds = [];
        if (is_string($caseSessionId)) array_push($sessionIds, $caseSessionId);
        if (is_array($caseSessionId)) {
            foreach ($caseSessionId as $sessionId) array_push($sessionIds, $sessionId);
        }

        $sessions = [];
        foreach ($sessionIds as $sessionId) {
            array_push($sessions, $this->getSessionById($sessionId, $case['CaseDetail']['CaseId']));
        }
        return $sessions;
    }

    /**
     * Get sessions from cases
     */
    public function getSessionsFromCases($cases): array
    {
        if (empty($cases)) throw new \Exception('Cannot get Sessions from Cases without Cases.');

        $sessions = [];
        foreach ($cases as $case) {
            array_push($sessions, $this->getSessionsFromCase($case));
        }
        return $sessions;
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
        if (env('DETAILED_LOGGING'))
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
    public function getClientServices($clientId, Filters $filters)
    {
        // Search for cases related to this client first
        $searchFilters = new Filters(array_merge($filters->byResource(ResourceType::CLIENT), ['ClientId' => $clientId]));
        $parameters = [
            'OrganisationId' => config('soap.dss.organisation_id'),
            'Filters' => $this->formatFilters($searchFilters)
        ];

        return $this->soapClient->call('SearchCase', $parameters);
    }

    /**
     * Get data export in specified format
     */
    public function exportData($resourceType, Filters $filters, $format = 'xml')
    {
        $parameters = [
            'OrganisationId' => config('soap.dss.organisation_id'),
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
            'OrganisationId' => config('soap.dss.organisation_id'),
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
        return $this->getDefaultSchemaInfo(ResourceType::resolve($resourceType));
    }

    /**
     * Provide default schema information when SOAP schema methods don't exist
     */
    protected function getDefaultSchemaInfo(ResourceType $resourceType)
    {
        $schemas = [
            ResourceType::CLIENT->value => [
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
            ResourceType::CASE->value => [
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
                    'CreatedDateFrom' => 'Search from date (ISO format)',
                    'CreatedDateTo' => 'Search to date (ISO format)'
                ]
            ],
            ResourceType::FULL_CASE->value => [
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
                    'CreatedDateFrom' => 'Search from date (ISO format)',
                    'CreatedDateTo' => 'Search to date (ISO format)'
                ],
                'note' => 'This method performs multiple SOAP calls and may take longer than standard SearchCase'
            ],
            ResourceType::FULL_SESSION->value => [
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
                    'CreatedDateFrom' => 'Search from date (ISO format)',
                    'CreatedDateTo' => 'Search to date (ISO format)'
                ],
                'note' => 'This method performs the most SOAP calls (SearchCase + GetCase for each case + GetSession for each session) and will take the longest time but provides the most comprehensive session data'
            ],
            ResourceType::SESSION->value => [
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
    protected function formatFilters(Filters $filters)
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
     * Format search criteria
     */
    protected function formatSearchCriteria(Filters $filters, ResourceType $type)
    {
        $criteria = [];

        $resourceFilters = $filters->byResource($type);

        foreach ($resourceFilters as $key => $value) {
            $criteria[FilterType::getDexFilter($key)] = $value;
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

        if (!empty($filters['service_type'])) {
            $criteria['ServiceType'] = $filters['service_type'];
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
    public function convertDataFormat($data, string $format = 'json')
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
    public function arrayToCsv($data)
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
     * Get valid country codes from DSS reference data
     */
    protected function getValidCountryCodes()
    {
        static $countryCodes = null;

        if ($countryCodes === null) {
            try {
                $referenceData = $this->getReferenceData('CountryCode');
                $countryCodes = $this->parseReferenceData($referenceData);
            } catch (\Exception $e) {
                Log::warning('Failed to fetch country reference data: ' . $e->getMessage());
                $countryCodes = false; // Cache the failure
            }
        }

        return $countryCodes ?: null;
    }

    /**
     * Get valid language codes from DSS reference data
     */
    protected function getValidLanguageCodes()
    {
        static $languageCodes = null;

        if ($languageCodes === null) {
            try {
                $referenceData = $this->getReferenceData('LanguageCode');
                $languageCodes = $this->parseReferenceData($referenceData);
            } catch (\Exception $e) {
                Log::warning('Failed to fetch language reference data: ' . $e->getMessage());
                $languageCodes = false; // Cache the failure
            }
        }

        return $languageCodes ?: null;
    }

    /**
     * Parse reference data response into key-value pairs
     */
    protected function parseReferenceData($referenceData)
    {
        $parsed = [];

        if (is_object($referenceData)) {
            $referenceData = json_decode(json_encode($referenceData), true);
        }

        // Parse the DSS reference data structure
        // Handle the format: Data.ReferenceData array with Code/Description pairs
        if (isset($referenceData['Data']['ReferenceData']) && is_array($referenceData['Data']['ReferenceData'])) {
            foreach ($referenceData['Data']['ReferenceData'] as $item) {
                if (isset($item['Code']) && isset($item['Description'])) {
                    $parsed[$item['Description']] = $item['Code'];
                }
            }
        }
        // Fallback for other possible formats
        elseif (isset($referenceData['ReferenceItems']) && is_array($referenceData['ReferenceItems'])) {
            foreach ($referenceData['ReferenceItems'] as $item) {
                if (isset($item['Code']) && isset($item['Description'])) {
                    $parsed[$item['Description']] = $item['Code'];
                }
            }
        }
        // Handle direct array format
        elseif (is_array($referenceData) && isset($referenceData[0]['Code'])) {
            foreach ($referenceData as $item) {
                if (isset($item['Code']) && isset($item['Description'])) {
                    $parsed[$item['Description']] = $item['Code'];
                }
            }
        }

        return $parsed;
    }

    /**
     * Get valid ATSI codes from DSS reference data
     */
    protected function getValidATSICodes()
    {
        static $atsiCodes = null;

        if ($atsiCodes === null) {
            try {
                $referenceData = $this->getReferenceData('AboriginalOrTorresStraitIslanderOrigin');
                $atsiCodes = $this->parseReferenceData($referenceData);
            } catch (\Exception $e) {
                Log::warning('Failed to fetch ATSI reference data: ' . $e->getMessage());
                $atsiCodes = false; // Cache the failure
            }
        }

        return $atsiCodes ?: null;
    }

    /**
     * Get a safe ATSI status for fake data generation
     */
    protected function getSafeATSIForFakeData()
    {
        try {
            $atsiOptions = \App\Helpers\ReferenceData::aboriginalOrTorresStraitIslanderOrigin();

            if (!empty($atsiOptions)) {
                // Use the weighted random selection to favor non-indigenous
                $weights = [
                    'NO' => 80,  // 80% chance - most common
                    'NOTSTATED' => 15,  // 15% chance
                    'ABORIGINAL' => 3,  // 3% chance
                    'TSI' => 1,  // 1% chance
                    'BOTH' => 1  // 1% chance
                ];

                $randomValue = fake()->numberBetween(1, 100);
                $cumulative = 0;

                foreach ($weights as $code => $weight) {
                    $cumulative += $weight;
                    if ($randomValue <= $cumulative) {
                        // Find the option with this code
                        foreach ($atsiOptions as $option) {
                            if ($option->Code === $code) {
                                return $option->Code;
                            }
                        }
                    }
                }

                // Fallback to first option if weighting fails
                return $atsiOptions[0]->Code;
            }
        } catch (\Exception $e) {
            // Log error but continue with fallback
            Log::warning('Failed to get ATSI options from ReferenceData helper: ' . $e->getMessage());
        }

        // Fallback to safest default
        return 'NO';
    }

    /**
     * Get a safe country for fake data generation
     */
    protected function getSafeCountryForFakeData()
    {
        try {
            $countries = \App\Helpers\ReferenceData::countries();

            if (!empty($countries)) {
                // Look for Australia first (most common)
                foreach ($countries as $country) {
                    if (stripos($country->Description, 'Australia') !== false) {
                        return $country->Code;
                    }
                }

                // Use weighted selection for realistic distribution
                $commonCountries = ['1101', '2102', '1201', '2201']; // Australia, UK, NZ, USA
                $randomValue = fake()->numberBetween(1, 100);

                if ($randomValue <= 70) {
                    // 70% chance of common countries
                    foreach ($countries as $country) {
                        if (in_array($country->Code, $commonCountries)) {
                            return $country->Code;
                        }
                    }
                }

                // Otherwise random country
                return $countries[array_rand($countries)]->Code;
            }
        } catch (\Exception $e) {
            // Log error but continue with fallback
            Log::warning('Failed to get countries from ReferenceData helper: ' . $e->getMessage());
        }

        // Fallback to Australia code
        return '1101';
    }

    /**
     * Get a safe language for fake data generation
     */
    protected function getSafeLanguageForFakeData()
    {
        try {
            $languages = \App\Helpers\ReferenceData::languages();

            if (!empty($languages)) {
                // Look for English first (most common in Australia)
                foreach ($languages as $language) {
                    if (stripos($language->Description, 'English') !== false) {
                        return $language->Code;
                    }
                }

                // Use weighted selection for realistic language distribution in Australia
                $commonLanguages = [];
                $languagePriorities = ['English', 'Mandarin', 'Arabic', 'Vietnamese', 'Italian', 'Greek'];

                foreach ($languagePriorities as $priority) {
                    foreach ($languages as $language) {
                        if (stripos($language->Description, $priority) !== false) {
                            $commonLanguages[] = $language;
                            break;
                        }
                    }
                }

                if (!empty($commonLanguages)) {
                    // 80% chance of common languages
                    $randomValue = fake()->numberBetween(1, 100);
                    if ($randomValue <= 80) {
                        return $commonLanguages[array_rand($commonLanguages)]->Code;
                    }
                }

                // Otherwise random language
                return $languages[array_rand($languages)]->Code;
            }
        } catch (\Exception $e) {
            // Log error but continue with fallback
            Log::warning('Failed to get languages from ReferenceData helper: ' . $e->getMessage());
        }

        // Fallback to English code
        return '1201';
    }

    /**
     * Get a safe referral source for fake data generation
     */
    protected function getSafeReferralSourceForFakeData()
    {
        try {
            $referralSources = \App\Helpers\ReferenceData::referralSource();

            if (!empty($referralSources)) {
                // Use weighted selection for realistic distribution
                $commonSources = ['SELF', 'FAMILY', 'COMMUNITY', 'GP', 'HealthAgency'];
                $randomValue = fake()->numberBetween(1, 100);

                if ($randomValue <= 70) {
                    // 70% chance of common referral sources
                    foreach ($referralSources as $source) {
                        if (in_array($source->Code, $commonSources)) {
                            return $source->Code;
                        }
                    }
                }

                // Otherwise random referral source
                return $referralSources[array_rand($referralSources)]->Code;
            }
        } catch (\Exception $e) {
            // Log error but continue with fallback
            Log::warning('Failed to get referral sources from ReferenceData helper: ' . $e->getMessage());
        }

        // Fallback to common referral source
        return 'SELF';
    }

    /**
     * Get a safe attendance profile code from ReferenceData helper for fake data generation
     */
    protected function getSafeAttendanceProfileForFakeData()
    {
        return fake()->randomElement(DataExchangeController::ATTENDANCE_PROFILE_CODES);
    }

    /**
     * Get a safe exit reason code from ReferenceData helper for fake data generation
     */
    protected function getSafeExitReasonForFakeData()
    {
        try {
            $exitReasons = \App\Helpers\ReferenceData::exitReason();

            if (!empty($exitReasons)) {
                // Use weighted selection for realistic distribution
                $commonReasons = ['NEEDSMET', 'NOLONGERASSIST', 'MOVED'];
                $randomValue = fake()->numberBetween(1, 100);

                if ($randomValue <= 70) {
                    // 70% chance of common exit reasons
                    foreach ($exitReasons as $reason) {
                        if (in_array($reason->Code, $commonReasons)) {
                            return $reason->Code;
                        }
                    }
                }

                // Otherwise random exit reason
                return $exitReasons[array_rand($exitReasons)]->Code;
            }
        } catch (\Exception $e) {
            // Log error but continue with fallback
            Log::warning('Failed to get exit reasons from ReferenceData helper: ' . $e->getMessage());
        }

        // Fallback to needs met
        return 'NEEDSMET';
    }

    /**
     * Get existing client IDs from the DSS system for use in fake data
     */
    protected function getExistingClientIds($limit = 50)
    {
        static $cachedClientIds = null;

        if ($cachedClientIds === null) {
            try {
                // Search for clients with minimal criteria to get a broad set
                $searchResult = $this->getClientData(new Filters([
                    'page_index' => 1,
                    'page_size' => $limit,
                    'sort_column' => 'ClientId',
                    'is_ascending' => true
                ]));

                $clientIds = [];

                // Convert objects to arrays for consistent handling
                if (is_object($searchResult)) {
                    $searchResult = json_decode(json_encode($searchResult), true);
                }

                // Extract client IDs from the response
                if (isset($searchResult['Clients']['Client'])) {
                    $clients = $searchResult['Clients']['Client'];
                    // Ensure it's an array (single client comes as object)
                    if (!is_array($clients) || (is_array($clients) && isset($clients['ClientId']))) {
                        $clients = [$clients];
                    }

                    foreach ($clients as $client) {
                        if (isset($client['ClientId']) && !empty($client['ClientId'])) {
                            $clientIds[] = $client['ClientId'];
                        }
                    }
                }

                $cachedClientIds = !empty($clientIds) ? $clientIds : false;

                if ($cachedClientIds) {
                    if (env('DETAILED_LOGGING'))
                        Log::info('Retrieved existing client IDs for fake data', [
                            'count' => count($cachedClientIds),
                            'sample_ids' => array_slice($cachedClientIds, 0, 5)
                        ]);
                } else {
                    Log::warning('No existing client IDs found in DSS system');
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch existing client IDs: ' . $e->getMessage());
                $cachedClientIds = false; // Cache the failure
            }
        }

        return $cachedClientIds ?: [];
    }

    /**
     * Get existing case IDs from DSS system for use in fake data generation
     */
    protected function getExistingCaseIds($limit = 50)
    {
        static $cachedCaseIds = null;

        if ($cachedCaseIds === null) {
            try {
                // Search for cases with minimal criteria to get a broad set
                $searchResult = $this->getCaseData(new Filters([
                    'page_index' => 1,
                    'page_size' => $limit,
                    'sort_column' => 'CaseId',
                    'is_ascending' => true
                ]));

                $caseIds = [];

                // Convert objects to arrays for consistent handling
                if (is_object($searchResult)) {
                    $searchResult = json_decode(json_encode($searchResult), true);
                }

                // Extract case IDs from the response
                if (isset($searchResult['Cases']['Case'])) {
                    $cases = $searchResult['Cases']['Case'];
                    // Ensure it's an array (single case comes as object)
                    if (!is_array($cases) || (is_array($cases) && isset($cases['CaseId']))) {
                        $cases = [$cases];
                    }

                    foreach ($cases as $case) {
                        if (isset($case['CaseId']) && !empty($case['CaseId'])) {
                            $caseIds[] = $case['CaseId'];
                        } elseif (isset($case['CaseDetail']['CaseId']) && !empty($case['CaseDetail']['CaseId'])) {
                            $caseIds[] = $case['CaseDetail']['CaseId'];
                        }
                    }
                }

                $cachedCaseIds = !empty($caseIds) ? $caseIds : false;

                if ($cachedCaseIds) {
                    if (env('DETAILED_LOGGING'))
                        Log::info('Retrieved existing case IDs for fake data', [
                            'count' => count($cachedCaseIds),
                            'sample_ids' => array_slice($cachedCaseIds, 0, 5)
                        ]);
                } else {
                    Log::warning('No existing case IDs found in DSS system');
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch existing case IDs: ' . $e->getMessage());
                $cachedCaseIds = false; // Cache the failure
            }
        }

        return $cachedCaseIds ?: [];
    }

    /**
     * Generate multiple fake client records for bulk upload
     */
    public function generateFakeClientData($count = 10)
    {
        $clients = [];
        fake()->unique(true); // Reset unique generator

        for ($i = 0; $i < $count; $i++) {
            $clients[] = $this->generateSampleClientData();
        }

        return $clients;
    }

    /**
     * Generate multiple fake case records for bulk upload
     */
    public function generateFakeCaseData($count = 10, $clientIds = null)
    {
        $cases = [];
        fake()->unique(true); // Reset unique generator

        for ($i = 0; $i < $count; $i++) {
            $clientId = $clientIds ? ($clientIds[$i % count($clientIds)] ?? null) : null;
            $cases[] = $this->generateSampleCaseData($clientId);
        }

        return $cases;
    }

    /**
     * Generate multiple fake session records for bulk upload
     */
    public function generateFakeSessionData($count = 10, $caseIds = null)
    {
        $sessions = [];
        fake()->unique(true); // Reset unique generator

        for ($i = 0; $i < $count; $i++) {
            $caseId = $caseIds ? ($caseIds[$i % count($caseIds)] ?? null) : null;
            $sessions[] = $this->generateSampleSessionData($caseId);
        }

        return $sessions;
    }

    /**
     * Generate CSV content for fake data
     */
    public function generateFakeCSV(ResourceType $type, $count = 10, $relatedIds = null)
    {
        switch ($type) {
            case ResourceType::CLIENT:
                $data = $this->generateFakeClientData($count);
                break;
            case ResourceType::CASE:
                $data = $this->generateFakeCaseData($count, $relatedIds);
                break;
            case ResourceType::SESSION:
                $data = $this->generateFakeSessionData($count, $relatedIds);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported type: {$type}");
        }

        return $this->arrayToCsv($data);
    }

    /**
     * Get available resource types
     */
    public function getAvailableResources()
    {
        return [
            ResourceType::CLIENT->value => 'Client Data',
            ResourceType::CASE->value => 'Case Data',
            ResourceType::FULL_CASE->value => 'Fetch Full Case Data (SearchCase + GetCase)',
            ResourceType::FULL_SESSION->value => 'Fetch Full Session Data (SearchCase + GetCase + GetSession)',
            ResourceType::SESSION->value => 'Session Data (requires Case ID)'
        ];
    }

    /**
     * Submit case data to DSS Data Exchange
     */
    public function submitCaseData($caseData)
    {
        if (env('DETAILED_LOGGING'))
            Log::info('Starting AddCase request', [
                'case_id' => $caseData['case_id'] ?? 'unknown',
                'input_data_keys' => array_keys($caseData),
                'clients_count' => isset($caseData['clients']) ? count($caseData['clients']) : 0
            ]);

        try {
            $formattedCase = $this->formatCaseData($caseData);
            $formattedClients = $this->formatCaseClients($caseData);

            $parameters = [
                'Case' => $formattedCase,
                'Clients' => $formattedClients
            ];

            if (env('DETAILED_LOGGING'))
                Log::info('AddCase formatted parameters', [
                    'case_id' => $caseData['case_id'] ?? 'unknown',
                    'formatted_case_keys' => array_keys($formattedCase),
                    'formatted_clients_count' => count($formattedClients),
                    'full_parameters' => $parameters
                ]);

            $result = $this->soapClient->call('AddCase', $parameters);

            // Log the response
            if (is_object($result)) {
                $resultArray = json_decode(json_encode($result), true);
            } else {
                $resultArray = $result;
            }

            $success = isset($resultArray['TransactionStatus']['TransactionStatusCode']) &&
                $resultArray['TransactionStatus']['TransactionStatusCode'] === 'Success';

            if (env('DETAILED_LOGGING'))
                Log::info('AddCase response received', [
                    'case_id' => $caseData['case_id'] ?? 'unknown',
                    'success' => $success,
                    'status_code' => $resultArray['TransactionStatus']['TransactionStatusCode'] ?? 'unknown',
                    'messages' => $resultArray['TransactionStatus']['Messages'] ?? null,
                    'full_response' => $resultArray
                ]);

            if (!$success) {
                Log::error('AddCase failed', [
                    'case_id' => $caseData['case_id'] ?? 'unknown',
                    'error_details' => $resultArray['TransactionStatus'] ?? 'unknown_error',
                    'input_data' => $caseData
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('AddCase exception occurred', [
                'case_id' => $caseData['case_id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input_data' => $caseData
            ]);
            throw $e;
        }
    }

    /**
     * Format case clients data according to DSS specifications
     */
    protected function formatCaseClients($data)
    {
        $clients = [];

        // Handle single client or array of clients
        $clientData = $data['clients'] ?? [];
        if (isset($data['client_id']) && !empty($data['client_id'])) {
            // Single client case - use the client_id from the case data
            $clientData = [[
                'client_id' => $data['client_id'],
                'reasons_for_assistance' => $data['reasons_for_assistance'] ?? [],
                'referral_source_code' => $data['referral_source_code'] ?? 'COMMUNITY',
                'exit_reason_code' => $data['exit_reason_code'] ?? null
            ]];
        }

        foreach ($clientData as $client) {
            $caseClient = [
                'ClientId' => $client['client_id'] ?? null,
                'ReferralSourceCode' => $client['referral_source_code'] ?? 'COMMUNITY'
            ];

            // Format reasons for assistance
            if (!empty($client['reasons_for_assistance'])) {
                $reasons = [];
                foreach ($client['reasons_for_assistance'] as $reason) {
                    $reasons[] = [
                        'AssistanceNeededCode' => $reason['assistance_needed_code'] ?? 'PHYSICAL',
                        // Hardcoding IsPrimary to true as issues are caused by sending false where
                        // secondary reasons are sent
                        'IsPrimary' => true
                        // 'IsPrimary' => !empty($reason['is_primary'])
                    ];
                }
                $caseClient['ReasonsForAssistance'] = $reasons;
            }

            // Optional exit reason
            if (!empty($client['exit_reason_code'])) {
                $caseClient['ExitReasonCode'] = $client['exit_reason_code'];
            }

            $clients[] = $caseClient;
        }

        return $clients;
    }

    /**
     * Submit session data to DSS Data Exchange
     */
    public function submitSessionData($sessionData)
    {
        $parameters = [
            'CaseId' => $sessionData['case_id'],
            'Session' => $this->formatSessionData($sessionData),
        ];

        return $this->soapClient->call('AddSession', $parameters);
    }

    /**
     * Generate report based on report type and filters
     */
    public function generateReport($reportType, Filters $filters)
    {
        $parameters = [
            'OrganisationId' => config('soap.dss.organisation_id'),
            'ReportType' => $reportType,
            'Filters' => $this->formatFilters($filters)
        ];

        return $this->soapClient->call('GenerateReport', $parameters);
    }

    /**
     * Get available outlet activities for the organization
     */
    public function getOutletActivities()
    {
        $parameters = [
            'OrganisationId' => config('soap.dss.organisation_id')
        ];

        return $this->soapClient->call('GetOutletActivities', $parameters);
    }

    /**
     * Update client data
     */
    public function updateClient($clientId, $clientData)
    {
        $parameters = [
            'Client' => array_merge(
                $this->formatClientData($clientData),
                ['ClientId' => $clientId]
            )
        ];

        return $this->soapClient->call('UpdateClient', $parameters);
    }

    /**
     * Delete client
     */
    public function deleteClient($clientId)
    {
        $parameters = [
            'ClientId' => $clientId,
            'OrganisationId' => config('soap.dss.organisation_id')
        ];

        return $this->soapClient->call('DeleteClient', $parameters);
    }

    /**
     * Update case data
     */
    public function updateCase($caseId, $caseData)
    {
        $parameters = [
            'Case' => array_merge(
                $this->formatCaseData($caseData),
                ['CaseId' => $caseId]
            )
        ];

        return $this->soapClient->call('UpdateCase', $parameters);
    }

    /**
     * Delete case
     */
    public function deleteCase($caseId)
    {
        $parameters = [
            'CaseId' => $caseId,
            'OrganisationId' => config('soap.dss.organisation_id')
        ];

        return $this->soapClient->call('DeleteCase', $parameters);
    }

    /**
     * Update session data
     */
    public function updateSession($sessionId, $sessionData)
    {
        $parameters = [
            'Session' => array_merge(
                $this->formatSessionData($sessionData),
                ['SessionId' => $sessionId]
            )
        ];

        return $this->soapClient->call('UpdateSession', $parameters);
    }

    /**
     * Delete session
     */
    public function deleteSession($sessionId)
    {
        $parameters = [
            'SessionId' => $sessionId,
            'OrganisationId' => config('soap.dss.organisation_id')
        ];

        return $this->soapClient->call('DeleteSession', $parameters);
    }
}
