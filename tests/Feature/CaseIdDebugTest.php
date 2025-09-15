<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\DataExchangeService;
use App\Services\SoapClientService;
use Mockery;

class CaseIdDebugTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Debug test to see exactly what happens during case_by_id form submission
     */
    public function test_debug_case_by_id_form_submission()
    {
        // Create a real instance to see what actually gets called
        $mockSoapClient = Mockery::mock(SoapClientService::class);

        // Expect the service to be called with correct parameters
        $mockSoapClient->shouldReceive('call')
            ->with('GetCase', [
                'CaseId' => 'DEBUG_CASE_123',
                'Criteria' => []
            ])
            ->once()
            ->andReturn([
                'CaseId' => 'DEBUG_CASE_123',
                'Status' => 'Open'
            ]);

        // Create real service instance
        $dataService = new DataExchangeService($mockSoapClient);
        $this->app->instance(DataExchangeService::class, $dataService);

        // Submit form exactly as it would be from the browser
        $response = $this->post(route('data-exchange.retrieve-data'), [
            '_token' => csrf_token(),
            'resource_type' => 'case_by_id',
            'format' => 'json',
            'case_id' => 'DEBUG_CASE_123',
            'action' => 'preview'
        ]);

        // Check what we get
        if (session('error')) {
            $this->fail('Got error: ' . session('error'));
        }

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('data');

        $data = session('data');
        $this->assertIsArray($data);
        $this->assertEquals('DEBUG_CASE_123', $data['CaseId']);
    }

    /**
     * Test with empty string case_id to see if that's the issue
     */
    public function test_empty_string_case_id()
    {
        $response = $this->post(route('data-exchange.retrieve-data'), [
            '_token' => csrf_token(),
            'resource_type' => 'case_by_id',
            'format' => 'json',
            'case_id' => '', // Empty string
            'action' => 'preview'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $errorMessage = session('error');
        $this->assertStringContainsString('Case ID is required', $errorMessage);
    }

    /**
     * Test with whitespace-only case_id
     */
    public function test_whitespace_only_case_id()
    {
        $response = $this->post(route('data-exchange.retrieve-data'), [
            '_token' => csrf_token(),
            'resource_type' => 'case_by_id',
            'format' => 'json',
            'case_id' => '   ', // Whitespace only
            'action' => 'preview'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $errorMessage = session('error');
        $this->assertStringContainsString('Case ID is required', $errorMessage);
    }

    /**
     * Test the actual form to see if it contains the right field names
     */
    public function test_form_contains_correct_field_names()
    {
        $response = $this->get(route('data-exchange.retrieve-form'));

        $response->assertStatus(200);

        // Check that the required case_id field is present
        $response->assertSee('name="case_id"', false);
        $response->assertSee('id="req_case_id"', false);
        $response->assertSee('Case ID', false);
        $response->assertSee('text-danger', false); // Required field indicator
    }

    /**
     * Test with actual form data structure that might be sent from browser
     */
    public function test_browser_like_form_submission()
    {
        // Mock the service
        $mockDataService = Mockery::mock(DataExchangeService::class);

        $mockDataService->shouldReceive('getCaseById')
            ->once()
            ->with('BROWSER_CASE_456')
            ->andReturn([
                'CaseId' => 'BROWSER_CASE_456',
                'Status' => 'Open'
            ]);

        $mockDataService->shouldReceive('getSanitizedLastRequest')->andReturn('Mock request');
        $mockDataService->shouldReceive('getSanitizedLastResponse')->andReturn('Mock response');

        $this->app->instance(DataExchangeService::class, $mockDataService);

        // Simulate exactly what a browser form would send
        $formData = [
            '_token' => csrf_token(),
            'resource_type' => 'case_by_id',
            'format' => 'json',
            'case_id' => 'BROWSER_CASE_456', // This should be the required field
            'action' => 'preview'
        ];

        // Debug: Print what we're sending
        echo "\nForm data being sent:\n";
        print_r($formData);

        $response = $this->post(route('data-exchange.retrieve-data'), $formData);

        // Debug: Print response and session
        if (session('error')) {
            echo "\nError received: " . session('error') . "\n";
        }
        if (session('success')) {
            echo "\nSuccess received: " . session('success') . "\n";
        }

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
