<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\DataExchangeService;
use App\Services\SoapClientService;
use Mockery;

class FetchFullCaseDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test fetchFullCaseData method with multiple cases
     */
    public function test_fetch_full_case_data_with_multiple_cases()
    {
        // Mock the SOAP client
        $mockSoapClient = Mockery::mock(SoapClientService::class);

        // Mock SearchCase call (returns basic case info - using arrays for consistency)
        // Note: fetchFullCaseData now uses dual strategy with 3 SearchCase calls
        $mockSoapClient->shouldReceive('call')
            ->with('SearchCase', Mockery::any())
            ->times(3)
            ->andReturn([
                'Cases' => [
                    'Case' => [
                        ['CaseId' => 'CASE001', 'Status' => 'Open'],
                        ['CaseId' => 'CASE002', 'Status' => 'Closed']
                    ]
                ]
            ]);

        // Mock GetCase calls (returns detailed case info with proper SOAP structure)
        $mockSoapClient->shouldReceive('call')
            ->with('GetCase', [
                'CaseId' => 'CASE001',
                'Criteria' => []
            ])
            ->once()
            ->andReturn([
                'TransactionStatus' => [
                    'TransactionStatusCode' => 'Success'
                ],
                'Case' => [
                    'CaseId' => 'CASE001',
                    'Status' => 'Open',
                    'ClientId' => 'CLIENT123',
                    'CaseType' => 'Counselling',
                    'CreatedDate' => '2024-01-01',
                    'DetailedInfo' => 'Full case details for CASE001'
                ]
            ]);

        $mockSoapClient->shouldReceive('call')
            ->with('GetCase', [
                'CaseId' => 'CASE002',
                'Criteria' => []
            ])
            ->once()
            ->andReturn([
                'TransactionStatus' => [
                    'TransactionStatusCode' => 'Success'
                ],
                'Case' => [
                    'CaseId' => 'CASE002',
                    'Status' => 'Closed',
                    'ClientId' => 'CLIENT456',
                    'CaseType' => 'Support',
                    'CreatedDate' => '2024-01-02',
                    'DetailedInfo' => 'Full case details for CASE002'
                ]
            ]);

        // Create service with mock
        $service = new DataExchangeService($mockSoapClient);

        // Test fetchFullCaseData
        $result = $service->fetchFullCaseData(['case_status' => 'Any']);

        // Verify structure - should have pagination metadata and cases data
        $this->assertIsArray($result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('Cases', $result);
        $this->assertArrayHasKey('Case', $result['Cases']);

        $cases = $result['Cases']['Case'];
        $this->assertCount(2, $cases);

        // Verify first case has detailed info
        $case1 = $cases[0];
        $this->assertEquals('CASE001', $case1['CaseId']);
        $this->assertEquals('CLIENT123', $case1['ClientId']);
        $this->assertArrayHasKey('DetailedInfo', $case1);

        // Verify second case has detailed info
        $case2 = $cases[1];
        $this->assertEquals('CASE002', $case2['CaseId']);
        $this->assertEquals('CLIENT456', $case2['ClientId']);
        $this->assertArrayHasKey('DetailedInfo', $case2);
    }

    /**
     * Test fetchFullCaseData with single case
     */
    public function test_fetch_full_case_data_with_single_case()
    {
        // Mock the SOAP client
        $mockSoapClient = Mockery::mock(SoapClientService::class);

        // Mock SearchCase call (returns single case)
        // Note: fetchFullCaseData now uses dual strategy with 3 SearchCase calls
        $mockSoapClient->shouldReceive('call')
            ->with('SearchCase', Mockery::any())
            ->times(3)
            ->andReturn([
                'Cases' => [
                    'Case' => ['CaseId' => 'SINGLE001', 'Status' => 'Open']
                ]
            ]);

        // Mock GetCase call
        $mockSoapClient->shouldReceive('call')
            ->with('GetCase', [
                'CaseId' => 'SINGLE001',
                'Criteria' => []
            ])
            ->once()
            ->andReturn([
                'TransactionStatus' => [
                    'TransactionStatusCode' => 'Success'
                ],
                'Case' => [
                    'CaseId' => 'SINGLE001',
                    'Status' => 'Open',
                    'ClientId' => 'CLIENT789',
                    'DetailedInfo' => 'Full details for single case'
                ]
            ]);

        // Create service with mock
        $service = new DataExchangeService($mockSoapClient);

        // Test fetchFullCaseData
        $result = $service->fetchFullCaseData(['case_id' => 'SINGLE001']);

        // Verify single case handling - should have pagination metadata and cases data
        $this->assertIsArray($result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('Cases', $result);
        $this->assertArrayHasKey('Case', $result['Cases']);

        $cases = $result['Cases']['Case'];
        $this->assertCount(1, $cases);

        $case = $cases[0];
        $this->assertEquals('SINGLE001', $case['CaseId']);
        $this->assertEquals('CLIENT789', $case['ClientId']);
        $this->assertArrayHasKey('DetailedInfo', $case);
    }

    /**
     * Test fetchFullCaseData when no cases found
     */
    public function test_fetch_full_case_data_no_cases_found()
    {
        // Mock the SOAP client
        $mockSoapClient = Mockery::mock(SoapClientService::class);

        // Mock SearchCase call (returns no cases)
        // Note: fetchFullCaseData now uses dual strategy with 3 SearchCase calls
        $mockSoapClient->shouldReceive('call')
            ->with('SearchCase', Mockery::any())
            ->times(3)
            ->andReturn([
                'Cases' => []
            ]);

        // Create service with mock
        $service = new DataExchangeService($mockSoapClient);

        // Test fetchFullCaseData
        $result = $service->fetchFullCaseData(['case_status' => 'NonExistent']);

        // Verify no cases handling - should have pagination metadata but empty cases
        $this->assertIsArray($result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('Cases', $result);
        // Cases may be an empty array or contain empty structure
        $this->assertTrue(
            empty($result['Cases']) ||
            (isset($result['Cases']['Case']) && empty($result['Cases']['Case']))
        );
    }

    /**
     * Test fetchFullCaseData with GetCase errors
     */
    public function test_fetch_full_case_data_with_getcase_errors()
    {
        // Mock the SOAP client
        $mockSoapClient = Mockery::mock(SoapClientService::class);

        // Mock SearchCase call
        // Note: fetchFullCaseData now uses dual strategy with 3 SearchCase calls
        $mockSoapClient->shouldReceive('call')
            ->with('SearchCase', Mockery::any())
            ->times(3)
            ->andReturn([
                'Cases' => [
                    'Case' => [
                        ['CaseId' => 'GOOD001', 'Status' => 'Open'],
                        ['CaseId' => 'BAD002', 'Status' => 'Error']
                    ]
                ]
            ]);

        // Mock successful GetCase call
        $mockSoapClient->shouldReceive('call')
            ->with('GetCase', [
                'CaseId' => 'GOOD001',
                'Criteria' => []
            ])
            ->once()
            ->andReturn([
                'TransactionStatus' => [
                    'TransactionStatusCode' => 'Success'
                ],
                'Case' => [
                    'CaseId' => 'GOOD001',
                    'DetailedInfo' => 'Success case'
                ]
            ]);

        // Mock failing GetCase call
        $mockSoapClient->shouldReceive('call')
            ->with('GetCase', [
                'CaseId' => 'BAD002',
                'Criteria' => []
            ])
            ->once()
            ->andThrow(new \Exception('SOAP call failed for BAD002'));

        // Create service with mock
        $service = new DataExchangeService($mockSoapClient);

        // Test fetchFullCaseData
        $result = $service->fetchFullCaseData([]);

        // Verify error handling - should have pagination metadata and only successful cases
        $this->assertIsArray($result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('Cases', $result);
        $this->assertArrayHasKey('Case', $result['Cases']);

        $cases = $result['Cases']['Case'];
        $this->assertCount(1, $cases); // Only successful case returned

        // Verify only the successful case is returned
        $successfulCase = $cases[0];
        $this->assertEquals('GOOD001', $successfulCase['CaseId']);
        $this->assertArrayHasKey('DetailedInfo', $successfulCase);

        // Failed cases should not be in the result
        foreach ($cases as $case) {
            $this->assertArrayNotHasKey('error', $case);
        }
    }

    /**
     * Test controller integration for fetchFullCaseData
     */
    public function test_controller_fetch_full_case_data()
    {
        // Mock the DataExchangeService
        $mockDataService = Mockery::mock(DataExchangeService::class);

        $mockDataService->shouldReceive('fetchFullCaseData')
            ->once()
            ->with(Mockery::on(function ($filters) {
                return isset($filters['case_status']) && $filters['case_status'] === 'Open';
            }))
            ->andReturn([
                [
                    'CaseId' => 'FULL001',
                    'ClientId' => 'CLIENT999',
                    'DetailedInfo' => 'Complete case information'
                ]
            ]);

        // Mock debug methods
        $mockDataService->shouldReceive('getSanitizedLastRequest')->andReturn('Mock request');
        $mockDataService->shouldReceive('getSanitizedLastResponse')->andReturn('Mock response');

        // Replace the service in the container
        $this->app->instance(DataExchangeService::class, $mockDataService);

        // Test controller
        $response = $this->post(route('data-exchange.retrieve-data'), [
            '_token' => csrf_token(),
            'resource_type' => 'full_cases',
            'format' => 'json',
            'case_status' => 'Open',
            'action' => 'preview'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('data');

        $data = session('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals('FULL001', $data[0]['CaseId']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
