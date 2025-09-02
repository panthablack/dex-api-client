<?php

namespace Tests\Feature;

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
     * Test session retrieval requires Case ID
     */
    public function test_session_retrieval_requires_case_id()
    {
        // Test without Case ID
        $response = $this->post(route('data-exchange.retrieve-data'), [
            '_token' => csrf_token(),
            'resource_type' => 'sessions',
            'format' => 'json',
            'action' => 'preview'
        ]);

        // Should redirect back with error
        $response->assertRedirect();
        $response->assertSessionHas('error');
        
        // Check error message contains Case ID requirement
        $errorMessage = session('error');
        $this->assertStringContainsString('Case ID is required', $errorMessage);
    }

    /**
     * Test session retrieval with valid Case ID
     */
    public function test_session_retrieval_with_valid_case_id()
    {
        // Mock the DataExchangeService with all required methods
        $mockDataService = Mockery::mock(DataExchangeService::class);
        
        // Mock the main method
        $mockDataService->shouldReceive('getSessionData')
            ->once()
            ->with(Mockery::on(function ($filters) {
                return isset($filters['case_id']) && $filters['case_id'] === 'TEST123';
            }))
            ->andReturn([
                'Sessions' => [
                    'Session' => [
                        'SessionId' => 'S001',
                        'CaseId' => 'TEST123',
                        'SessionType' => 'Counselling'
                    ]
                ]
            ]);
        
        // Mock debug methods
        $mockDataService->shouldReceive('getSanitizedLastRequest')->andReturn('Mock request');
        $mockDataService->shouldReceive('getSanitizedLastResponse')->andReturn('Mock response');

        // Replace the service in the container
        $this->app->instance(DataExchangeService::class, $mockDataService);

        // Test with Case ID
        $response = $this->post(route('data-exchange.retrieve-data'), [
            '_token' => csrf_token(),
            'resource_type' => 'sessions',
            'format' => 'json',
            'case_id' => 'TEST123',
            'action' => 'preview'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('data');
    }

    /**
     * Test that buildFilters includes case_id from request
     */
    public function test_build_filters_includes_case_id()
    {
        // Create a mock request
        $request = new \Illuminate\Http\Request([
            'resource_type' => 'sessions',
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
        $mockSoapClient->shouldReceive('call')
            ->with('SearchCase', Mockery::any())
            ->once()
            ->andReturn([
                'Sessions' => ['Session' => ['SessionId' => 'S789']]
            ]);

        // Create service with mock
        $service = new DataExchangeService($mockSoapClient);

        // Test with valid case ID
        $result = $service->getSessionData(['case_id' => 'CASE789']);
        
        $this->assertArrayHasKey('Sessions', $result);
        $this->assertArrayHasKey('Session', $result['Sessions']);
        $this->assertEquals('S789', $result['Sessions']['Session']['SessionId']);
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

    /**
     * Test form field conflicts don't affect session retrieval
     */
    public function test_form_field_conflicts_resolved()
    {
        // Mock the DataExchangeService
        $mockDataService = Mockery::mock(DataExchangeService::class);
        
        // Mock the main method
        $mockDataService->shouldReceive('getSessionData')
            ->once()
            ->with(Mockery::on(function ($filters) {
                return isset($filters['case_id']) && $filters['case_id'] === 'CONFLICT_TEST';
            }))
            ->andReturn([
                'Sessions' => [
                    'Session' => [
                        'SessionId' => 'S001',
                        'CaseId' => 'CONFLICT_TEST',
                        'SessionType' => 'Counselling'
                    ]
                ]
            ]);
        
        // Mock debug methods
        $mockDataService->shouldReceive('getSanitizedLastRequest')->andReturn('Mock request');
        $mockDataService->shouldReceive('getSanitizedLastResponse')->andReturn('Mock response');

        // Replace the service in the container
        $this->app->instance(DataExchangeService::class, $mockDataService);
        
        // Test that when both required and optional case_id fields might be present,
        // the system handles it correctly
        $response = $this->post(route('data-exchange.retrieve-data'), [
            '_token' => csrf_token(),
            'resource_type' => 'sessions',
            'format' => 'json',
            'case_id' => 'CONFLICT_TEST', // This should be used
            'action' => 'preview'
        ]);

        // Should not get the "Case ID required" error since we provided one
        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        // Check if we got past the Case ID validation
        if (session('error')) {
            $errorMessage = session('error');
            $this->assertStringNotContainsString('Case ID is required', $errorMessage);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}