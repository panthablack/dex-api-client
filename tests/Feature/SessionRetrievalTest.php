<?php

namespace Tests\Feature;

use App\Enums\ResourceType;
use Tests\TestCase;
use App\Services\DataExchangeService;
use App\Services\SoapClientService;
use Mockery;

class SessionRetrievalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test that session retrieval form shows required Case ID field
     */
    public function test_session_form_shows_required_case_id_field()
    {
        $response = $this->get(route('data-exchange.retrieve-form'));

        $response->assertStatus(200);
        $response->assertSee('req_case_id');
        $response->assertSee('Case ID');
        $response->assertSee('text-danger'); // Required field styling
    }

    /**
     * Test that buildFilters includes case_id from request
     */
    public function test_build_filters_includes_case_id()
    {
        // Create a mock request
        $request = new \Illuminate\Http\Request([
            'resource_type' => ResourceType::SESSION,
            'format' => 'json',
            'case_id' => 'TEST456',
            'action' => 'preview'
        ]);

        // Get the controller
        $controller = new \App\Http\Controllers\DataExchangeController(
            new DataExchangeService(new SoapClientService())
        );

        // Use reflection to access the protected buildFilters method
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('buildFilters');
        $method->setAccessible(true);

        $filters = $method->invokeArgs($controller, [$request]);

        $this->assertArrayHasKey('case_id', $filters);
        $this->assertEquals('TEST456', $filters['case_id']);
    }

    /**
     * Test DataExchangeService getSessionData method
     */
    public function test_data_exchange_service_get_session_data()
    {
        // Mock the SOAP client
        $mockSoapClient = Mockery::mock(SoapClientService::class);

        // Mock SearchCase call (first call)
        $mockSoapClient->shouldReceive('call')
            ->with('SearchCase', Mockery::any())
            ->times(1)
            ->andReturn([
                'Cases' => [
                    'Case' => [
                        'CaseId' => 'CASE789'
                    ]
                ]
            ]);

        // Mock GetCase call (second call for detailed data)
        $mockSoapClient->shouldReceive('call')
            ->with('GetCase', Mockery::any())
            ->once()
            ->andReturn([
                'TransactionStatus' => [
                    'TransactionStatusCode' => 'Success'
                ],
                'Case' => [
                    'CaseDetail' => [
                        'CaseId' => 'CASE789'
                    ],
                    'Sessions' => [
                        'SessionId' => 'S789'
                    ]
                ]
            ]);

        // Mock GetSession call (third call for session details)
        $mockSoapClient->shouldReceive('call')
            ->with('GetSession', Mockery::any())
            ->once()
            ->andReturn([
                'SessionDetail' => [
                    'SessionId' => 'S789',
                    'CaseId' => 'CASE789'
                ]
            ]);

        // Create service with mock
        $service = new DataExchangeService($mockSoapClient);

        // Test with valid case ID
        $result = $service->getSessionData(['case_id' => 'CASE789']);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test DataExchangeService throws exception without Case ID
     */
    public function test_data_exchange_service_throws_exception_without_case_id()
    {
        $mockSoapClient = Mockery::mock(SoapClientService::class);
        $service = new DataExchangeService($mockSoapClient);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Case ID is required');

        $service->getSessionData([]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
