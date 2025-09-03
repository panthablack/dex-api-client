<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\DataExchangeService;
use App\Services\SoapClientService;
use Mockery;
use Illuminate\Http\Request;

class GetByIdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test Get Client by ID form submission
     */
    public function test_get_client_by_id_form_submission()
    {
        // Mock the DataExchangeService
        $mockDataService = Mockery::mock(DataExchangeService::class);
        
        // Mock the getClientById method
        $mockDataService->shouldReceive('getClientById')
            ->once()
            ->with('CLIENT123')
            ->andReturn([
                'ClientId' => 'CLIENT123',
                'FirstName' => 'John',
                'LastName' => 'Doe'
            ]);
        
        // Mock debug methods
        $mockDataService->shouldReceive('getSanitizedLastRequest')->andReturn('Mock request');
        $mockDataService->shouldReceive('getSanitizedLastResponse')->andReturn('Mock response');

        // Replace the service in the container
        $this->app->instance(DataExchangeService::class, $mockDataService);

        // Submit the form with client_id
        $response = $this->post(route('data-exchange.retrieve-data'), [
            '_token' => csrf_token(),
            'resource_type' => 'client_by_id',
            'format' => 'json',
            'client_id' => 'CLIENT123',
            'action' => 'preview'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('data');
    }

    /**
     * Test Get Client by ID without client_id fails
     */
    public function test_get_client_by_id_without_client_id_fails()
    {
        $response = $this->post(route('data-exchange.retrieve-data'), [
            '_token' => csrf_token(),
            'resource_type' => 'client_by_id',
            'format' => 'json',
            'action' => 'preview'
            // Missing client_id
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        
        $errorMessage = session('error');
        $this->assertStringContainsString('Client ID is required', $errorMessage);
    }

    /**
     * Test Get Case by ID form submission
     */
    public function test_get_case_by_id_form_submission()
    {
        // Mock the DataExchangeService
        $mockDataService = Mockery::mock(DataExchangeService::class);
        
        // Mock the getCaseById method
        $mockDataService->shouldReceive('getCaseById')
            ->once()
            ->with('CASE123')
            ->andReturn([
                'CaseId' => 'CASE123',
                'ClientId' => 'CLIENT123',
                'CaseStatus' => 'Open'
            ]);
        
        // Mock debug methods
        $mockDataService->shouldReceive('getSanitizedLastRequest')->andReturn('Mock request');
        $mockDataService->shouldReceive('getSanitizedLastResponse')->andReturn('Mock response');

        // Replace the service in the container
        $this->app->instance(DataExchangeService::class, $mockDataService);

        // Submit the form with case_id
        $response = $this->post(route('data-exchange.retrieve-data'), [
            '_token' => csrf_token(),
            'resource_type' => 'case_by_id',
            'format' => 'json',
            'case_id' => 'CASE123',
            'action' => 'preview'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('data');
    }

    /**
     * Test Get Case by ID without case_id fails
     */
    public function test_get_case_by_id_without_case_id_fails()
    {
        $response = $this->post(route('data-exchange.retrieve-data'), [
            '_token' => csrf_token(),
            'resource_type' => 'case_by_id',
            'format' => 'json',
            'action' => 'preview'
            // Missing case_id
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        
        $errorMessage = session('error');
        $this->assertStringContainsString('Case ID is required', $errorMessage);
    }

    /**
     * Test Get Session by ID form submission
     */
    public function test_get_session_by_id_form_submission()
    {
        // Mock the DataExchangeService
        $mockDataService = Mockery::mock(DataExchangeService::class);
        
        // Mock the getSessionById method
        $mockDataService->shouldReceive('getSessionById')
            ->once()
            ->with('SESSION123', 'CASE456')
            ->andReturn([
                'SessionId' => 'SESSION123',
                'CaseId' => 'CASE456',
                'SessionType' => 'Counselling'
            ]);
        
        // Mock debug methods
        $mockDataService->shouldReceive('getSanitizedLastRequest')->andReturn('Mock request');
        $mockDataService->shouldReceive('getSanitizedLastResponse')->andReturn('Mock response');

        // Replace the service in the container
        $this->app->instance(DataExchangeService::class, $mockDataService);

        // Submit the form with both session_id and case_id
        $response = $this->post(route('data-exchange.retrieve-data'), [
            '_token' => csrf_token(),
            'resource_type' => 'session_by_id',
            'format' => 'json',
            'session_id' => 'SESSION123',
            'case_id' => 'CASE456',
            'action' => 'preview'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('data');
    }

    /**
     * Test Get Session by ID without session_id fails
     */
    public function test_get_session_by_id_without_session_id_fails()
    {
        $response = $this->post(route('data-exchange.retrieve-data'), [
            '_token' => csrf_token(),
            'resource_type' => 'session_by_id',
            'format' => 'json',
            'case_id' => 'CASE456',
            'action' => 'preview'
            // Missing session_id
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        
        $errorMessage = session('error');
        $this->assertStringContainsString('Session ID is required', $errorMessage);
    }

    /**
     * Test Get Session by ID without case_id fails
     */
    public function test_get_session_by_id_without_case_id_fails()
    {
        $response = $this->post(route('data-exchange.retrieve-data'), [
            '_token' => csrf_token(),
            'resource_type' => 'session_by_id',
            'format' => 'json',
            'session_id' => 'SESSION123',
            'action' => 'preview'
            // Missing case_id
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        
        $errorMessage = session('error');
        $this->assertStringContainsString('Case ID is required', $errorMessage);
    }

    /**
     * Test buildFilters method correctly processes request data
     */
    public function test_build_filters_processes_ids_correctly()
    {
        // Create requests with various ID fields
        $clientRequest = new Request([
            'resource_type' => 'client_by_id',
            'client_id' => 'CLIENT789',
            'format' => 'json'
        ]);

        $caseRequest = new Request([
            'resource_type' => 'case_by_id',
            'case_id' => 'CASE789',
            'format' => 'json'
        ]);

        $sessionRequest = new Request([
            'resource_type' => 'session_by_id',
            'session_id' => 'SESSION789',
            'case_id' => 'CASE789',
            'format' => 'json'
        ]);

        // Get the controller
        $controller = new \App\Http\Controllers\DataExchangeController(
            new DataExchangeService(new SoapClientService())
        );

        // Use reflection to access the protected buildFilters method
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('buildFilters');
        $method->setAccessible(true);

        // Test client filters
        $clientFilters = $method->invokeArgs($controller, [$clientRequest]);
        $this->assertArrayHasKey('client_id', $clientFilters);
        $this->assertEquals('CLIENT789', $clientFilters['client_id']);

        // Test case filters
        $caseFilters = $method->invokeArgs($controller, [$caseRequest]);
        $this->assertArrayHasKey('case_id', $caseFilters);
        $this->assertEquals('CASE789', $caseFilters['case_id']);

        // Test session filters
        $sessionFilters = $method->invokeArgs($controller, [$sessionRequest]);
        $this->assertArrayHasKey('session_id', $sessionFilters);
        $this->assertArrayHasKey('case_id', $sessionFilters);
        $this->assertEquals('SESSION789', $sessionFilters['session_id']);
        $this->assertEquals('CASE789', $sessionFilters['case_id']);
    }

    /**
     * Test that request parameters are properly accessible
     */
    public function test_request_parameter_accessibility()
    {
        // Test direct access to request parameters
        $request = new Request([
            'resource_type' => 'case_by_id',
            'case_id' => 'TEST_CASE_ID',
            'format' => 'json'
        ]);

        // Test various ways to access case_id
        $this->assertTrue($request->has('case_id'));
        $this->assertEquals('TEST_CASE_ID', $request->get('case_id'));
        $this->assertEquals('TEST_CASE_ID', $request->case_id);
        $this->assertFalse(empty($request->case_id));
        $this->assertNotNull($request->case_id);
    }

    /**
     * Test service methods with correct parameter structure
     */
    public function test_service_methods_parameter_structure()
    {
        // Mock the SOAP client
        $mockSoapClient = Mockery::mock(SoapClientService::class);
        
        // Test GetClient parameters
        $mockSoapClient->shouldReceive('call')
            ->with('GetClient', [
                'ClientId' => 'TEST_CLIENT',
                'Criteria' => []
            ])
            ->once()
            ->andReturn(['ClientId' => 'TEST_CLIENT']);

        // Test GetCase parameters
        $mockSoapClient->shouldReceive('call')
            ->with('GetCase', [
                'CaseId' => 'TEST_CASE',
                'Criteria' => []
            ])
            ->once()
            ->andReturn(['CaseId' => 'TEST_CASE']);

        // Test GetSession parameters
        $mockSoapClient->shouldReceive('call')
            ->with('GetSession', [
                'SessionId' => 'TEST_SESSION',
                'CaseId' => 'TEST_CASE',
                'Criteria' => []
            ])
            ->once()
            ->andReturn(['SessionId' => 'TEST_SESSION']);

        // Create service with mock
        $service = new DataExchangeService($mockSoapClient);

        // Test all methods
        $service->getClientById('TEST_CLIENT');
        $service->getCaseById('TEST_CASE');
        $service->getSessionById('TEST_SESSION', 'TEST_CASE');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}