<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\DataExchangeService;
use App\Services\SoapClientService;
use Mockery;

class FetchFullSessionDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test fetchFullSessionData method with multiple cases and sessions
     */
    public function test_fetch_full_session_data_with_multiple_cases_and_sessions()
    {
        // Mock the SOAP client
        $mockSoapClient = Mockery::mock(SoapClientService::class);

        // Mock SearchCase call
        $mockSoapClient->shouldReceive('call')
            ->with('SearchCase', Mockery::any())
            ->times(1)
            ->andReturn([
                'Cases' => [
                    'Case' => [
                        ['CaseId' => 'CASE001', 'Status' => 'Open'],
                        ['CaseId' => 'CASE002', 'Status' => 'Closed']
                    ]
                ]
            ]);

        // Mock GetCase calls (returns detailed case info with sessions)
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
                    'OutletName' => 'Test Outlet 1',
                    'ProgramActivityName' => 'Test Program 1',
                    'CaseDetail' => [
                        'CaseId' => 'CASE001'
                    ],
                    'Sessions' => [
                        'SessionId' => ['SESSION001', 'SESSION002']
                    ]
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
                    'OutletName' => 'Test Outlet 2',
                    'ProgramActivityName' => 'Test Program 2',
                    'CaseDetail' => [
                        'CaseId' => 'CASE002'
                    ],
                    'Sessions' => [
                        'SessionId' => 'SESSION003'
                    ]
                ]
            ]);

        // Mock GetSession calls
        $mockSoapClient->shouldReceive('call')
            ->with('GetSession', [
                'SessionId' => 'SESSION001',
                'CaseId' => 'CASE001',
                'Criteria' => []
            ])
            ->once()
            ->andReturn([
                'TransactionStatus' => [
                    'TransactionStatusCode' => 'Success'
                ],
                'Session' => [
                    'SessionId' => 'SESSION001',
                    'CaseId' => 'CASE001',
                    'SessionDate' => '2024-01-01',
                    'SessionDetails' => 'Detailed session 1 info'
                ]
            ]);

        $mockSoapClient->shouldReceive('call')
            ->with('GetSession', [
                'SessionId' => 'SESSION002',
                'CaseId' => 'CASE001',
                'Criteria' => []
            ])
            ->once()
            ->andReturn([
                'TransactionStatus' => [
                    'TransactionStatusCode' => 'Success'
                ],
                'Session' => [
                    'SessionId' => 'SESSION002',
                    'CaseId' => 'CASE001',
                    'SessionDate' => '2024-01-02',
                    'SessionDetails' => 'Detailed session 2 info'
                ]
            ]);

        $mockSoapClient->shouldReceive('call')
            ->with('GetSession', [
                'SessionId' => 'SESSION003',
                'CaseId' => 'CASE002',
                'Criteria' => []
            ])
            ->once()
            ->andReturn([
                'TransactionStatus' => [
                    'TransactionStatusCode' => 'Success'
                ],
                'Session' => [
                    'SessionId' => 'SESSION003',
                    'CaseId' => 'CASE002',
                    'SessionDate' => '2024-01-03',
                    'SessionDetails' => 'Detailed session 3 info'
                ]
            ]);

        // Create service with mock
        $service = new DataExchangeService($mockSoapClient);

        // Test fetchFullSessionData
        $result = $service->fetchFullSessionData(['case_status' => 'Any']);

        // Verify structure - should be a simple array of session data
        $this->assertIsArray($result);
        $this->assertCount(3, $result); // 3 sessions total

        // Verify first session
        $session1 = $result[0];
        $this->assertEquals('SESSION001', $session1['SessionId']);
        $this->assertEquals('CASE001', $session1['CaseId']);
        $this->assertArrayHasKey('_case_context', $session1);
        $this->assertEquals('CASE001', $session1['_case_context']['CaseId']);
        $this->assertEquals('Test Outlet 1', $session1['_case_context']['OutletName']);

        // Verify second session
        $session2 = $result[1];
        $this->assertEquals('SESSION002', $session2['SessionId']);

        // Verify third session
        $session3 = $result[2];
        $this->assertEquals('SESSION003', $session3['SessionId']);
        $this->assertEquals('CASE002', $session3['CaseId']);
        $this->assertEquals('Test Outlet 2', $session3['_case_context']['OutletName']);
    }

    /**
     * Test fetchFullSessionData with single case and session
     */
    public function test_fetch_full_session_data_with_single_case_and_session()
    {
        // Mock the SOAP client
        $mockSoapClient = Mockery::mock(SoapClientService::class);

        // Mock SearchCase call
        $mockSoapClient->shouldReceive('call')
            ->with('SearchCase', Mockery::any())
            ->times(1)
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
                    'OutletName' => 'Single Outlet',
                    'CaseDetail' => [
                        'CaseId' => 'SINGLE001'
                    ],
                    'Sessions' => [
                        'SessionId' => 'SINGLE_SESSION001'
                    ]
                ]
            ]);

        // Mock GetSession call
        $mockSoapClient->shouldReceive('call')
            ->with('GetSession', [
                'SessionId' => 'SINGLE_SESSION001',
                'CaseId' => 'SINGLE001',
                'Criteria' => []
            ])
            ->once()
            ->andReturn([
                'TransactionStatus' => [
                    'TransactionStatusCode' => 'Success'
                ],
                'Session' => [
                    'SessionId' => 'SINGLE_SESSION001',
                    'CaseId' => 'SINGLE001',
                    'SessionDetails' => 'Single session details'
                ]
            ]);

        // Create service with mock
        $service = new DataExchangeService($mockSoapClient);

        // Test fetchFullSessionData
        $result = $service->fetchFullSessionData(['case_id' => 'SINGLE001']);

        // Verify single session handling
        $this->assertIsArray($result);
        $this->assertCount(1, $result);

        $session = $result[0];
        $this->assertEquals('SINGLE_SESSION001', $session['SessionId']);
        $this->assertEquals('SINGLE001', $session['CaseId']);
        $this->assertArrayHasKey('_case_context', $session);
    }

    /**
     * Test fetchFullSessionData when no cases found
     */
    public function test_fetch_full_session_data_no_cases_found()
    {
        // Mock the SOAP client
        $mockSoapClient = Mockery::mock(SoapClientService::class);

        // Mock SearchCase call (returns no cases)
        $mockSoapClient->shouldReceive('call')
            ->with('SearchCase', Mockery::any())
            ->times(1)
            ->andReturn([
                'Cases' => []
            ]);

        // Create service with mock
        $service = new DataExchangeService($mockSoapClient);

        // Test fetchFullSessionData
        $result = $service->fetchFullSessionData(['case_status' => 'NonExistent']);

        // Verify no sessions handling - should return empty array
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test fetchFullSessionData with cases that have no sessions
     */
    public function test_fetch_full_session_data_cases_without_sessions()
    {
        // Mock the SOAP client
        $mockSoapClient = Mockery::mock(SoapClientService::class);

        // Mock SearchCase call
        $mockSoapClient->shouldReceive('call')
            ->with('SearchCase', Mockery::any())
            ->times(1)
            ->andReturn([
                'Cases' => [
                    'Case' => ['CaseId' => 'NO_SESSIONS', 'Status' => 'Open']
                ]
            ]);

        // Mock GetCase call (case without sessions)
        $mockSoapClient->shouldReceive('call')
            ->with('GetCase', [
                'CaseId' => 'NO_SESSIONS',
                'Criteria' => []
            ])
            ->once()
            ->andReturn([
                'TransactionStatus' => [
                    'TransactionStatusCode' => 'Success'
                ],
                'Case' => [
                    'CaseId' => 'NO_SESSIONS',
                    'OutletName' => 'Empty Case',
                    'CaseDetail' => [
                        'CaseId' => 'NO_SESSIONS'
                    ]
                    // No Sessions field
                ]
            ]);

        // Create service with mock
        $service = new DataExchangeService($mockSoapClient);

        // Test fetchFullSessionData
        $result = $service->fetchFullSessionData([]);

        // Verify cases without sessions handling
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test fetchFullSessionData with GetSession errors
     */
    public function test_fetch_full_session_data_with_getsession_errors()
    {
        // Mock the SOAP client
        $mockSoapClient = Mockery::mock(SoapClientService::class);

        // Mock SearchCase call
        $mockSoapClient->shouldReceive('call')
            ->with('SearchCase', Mockery::any())
            ->times(1)
            ->andReturn([
                'Cases' => [
                    'Case' => ['CaseId' => 'ERROR_CASE', 'Status' => 'Open']
                ]
            ]);

        // Mock GetCase call
        $mockSoapClient->shouldReceive('call')
            ->with('GetCase', [
                'CaseId' => 'ERROR_CASE',
                'Criteria' => []
            ])
            ->once()
            ->andReturn([
                'TransactionStatus' => [
                    'TransactionStatusCode' => 'Success'
                ],
                'Case' => [
                    'CaseId' => 'ERROR_CASE',
                    'CaseDetail' => [
                        'CaseId' => 'ERROR_CASE'
                    ],
                    'Sessions' => [
                        'SessionId' => 'FAILING_SESSION'
                    ]
                ]
            ]);

        // Mock failing GetSession call
        $mockSoapClient->shouldReceive('call')
            ->with('GetSession', [
                'SessionId' => 'FAILING_SESSION',
                'CaseId' => 'ERROR_CASE',
                'Criteria' => []
            ])
            ->once()
            ->andThrow(new \Exception('SOAP call failed for session'));

        // Create service with mock
        $service = new DataExchangeService($mockSoapClient);

        // Test fetchFullSessionData
        $result = $service->fetchFullSessionData([]);

        // Verify error handling - only successful sessions are returned
        $this->assertIsArray($result);
        $this->assertEmpty($result); // No successful sessions

        // Verify no failed sessions in the result
        foreach ($result as $session) {
            $this->assertArrayNotHasKey('error', $session);
        }
    }

    /**
     * Test controller integration for fetchFullSessionData
     */
    public function test_controller_fetch_full_session_data()
    {
        // Mock the DataExchangeService
        $mockDataService = Mockery::mock(DataExchangeService::class);

        $mockDataService->shouldReceive('fetchFullSessionData')
            ->once()
            ->with(Mockery::on(function ($filters) {
                return isset($filters['case_status']) && $filters['case_status'] === 'Open';
            }))
            ->andReturn([
                [
                    'SessionId' => 'CONTROLLER001',
                    'CaseId' => 'CASE999',
                    'SessionDetails' => 'Controller test session',
                    '_case_context' => [
                        'CaseId' => 'CASE999',
                        'OutletName' => 'Controller Outlet'
                    ]
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
            'resource_type' => 'full_sessions',
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
        $this->assertEquals('CONTROLLER001', $data[0]['SessionId']);
        $this->assertArrayHasKey('_case_context', $data[0]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
