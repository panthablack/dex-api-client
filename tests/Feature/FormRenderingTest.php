<?php

namespace Tests\Feature;

use Tests\TestCase;

class FormRenderingTest extends TestCase
{
    /**
     * Test that the retrieve form loads correctly
     */
    public function test_retrieve_form_loads()
    {
        $response = $this->get(route('data-exchange.retrieve-form'));
        
        $response->assertStatus(200);
        $response->assertSee('Data Retrieval Form');
    }

    /**
     * Test that all required ID fields are present in the form
     */
    public function test_all_required_id_fields_present()
    {
        $response = $this->get(route('data-exchange.retrieve-form'));
        
        $response->assertStatus(200);
        
        // Check for required Client ID field
        $response->assertSee('id="req_client_id"', false);
        $response->assertSee('name="client_id"', false);
        
        // Check for required Case ID field  
        $response->assertSee('id="req_case_id"', false);
        $response->assertSee('name="case_id"', false);
        
        // Check for required Session ID field
        $response->assertSee('id="req_session_id"', false);
        $response->assertSee('name="session_id"', false);
        
        // Check for required Session Case ID field
        $response->assertSee('id="req_session_case_id"', false);
    }

    /**
     * Test that resource type options are present
     */
    public function test_resource_type_options_present()
    {
        $response = $this->get(route('data-exchange.retrieve-form'));
        
        $response->assertStatus(200);
        
        // Check for the by-id options
        $response->assertSee('value="client_by_id"', false);
        $response->assertSee('value="case_by_id"', false);
        $response->assertSee('value="session_by_id"', false);
        
        // Check for the text
        $response->assertSee('Get Client by ID');
        $response->assertSee('Get Case by ID');
        $response->assertSee('Get Session by ID');
    }

    /**
     * Test that JavaScript functions are present
     */
    public function test_javascript_functions_present()
    {
        $response = $this->get(route('data-exchange.retrieve-form'));
        
        $response->assertStatus(200);
        
        // Check for key JavaScript functions
        $response->assertSee('function updateFilters()', false);
        $response->assertSee('requiredSection.style.display', false);
        $response->assertSee('reqCaseId.required = true', false);
    }

    /**
     * Test that form doesn't have duplicate name attributes
     */
    public function test_no_duplicate_name_attributes()
    {
        $response = $this->get(route('data-exchange.retrieve-form'));
        $content = $response->getContent();
        
        // Count occurrences of name="case_id"
        $caseIdCount = substr_count($content, 'name="case_id"');
        
        // Should only appear twice: once in required filters, once in session required filters
        $this->assertEquals(2, $caseIdCount, "Found $caseIdCount occurrences of name=\"case_id\", expected 2");
        
        // Count occurrences of name="client_id" 
        $clientIdCount = substr_count($content, 'name="client_id"');
        
        // Should only appear once: in required filters (we removed the optional one)
        $this->assertEquals(1, $clientIdCount, "Found $clientIdCount occurrences of name=\"client_id\", expected 1");
        
        // Count occurrences of name="session_id"
        $sessionIdCount = substr_count($content, 'name="session_id"');
        
        // Should only appear once: in required filters (we removed the optional one)
        $this->assertEquals(1, $sessionIdCount, "Found $sessionIdCount occurrences of name=\"session_id\", expected 1");
    }

    /**
     * Test form field IDs are unique
     */
    public function test_form_field_ids_are_unique()
    {
        $response = $this->get(route('data-exchange.retrieve-form'));
        $content = $response->getContent();
        
        // Extract all id attributes
        preg_match_all('/id="([^"]+)"/', $content, $matches);
        $ids = $matches[1];
        
        // Check for duplicates
        $duplicates = array_diff_assoc($ids, array_unique($ids));
        
        $this->assertEmpty($duplicates, 'Found duplicate IDs: ' . implode(', ', $duplicates));
    }

    /**
     * Test that the required case_id field has the correct structure
     */
    public function test_required_case_id_field_structure()
    {
        $response = $this->get(route('data-exchange.retrieve-form'));
        $content = $response->getContent();
        
        // Look for the specific required case ID field
        $this->assertStringContainsString('id="req_case_id"', $content);
        $this->assertStringContainsString('name="case_id"', $content);
        $this->assertStringContainsString('required', $content);
        
        // Check that it's in the requiredCaseFilters div
        $this->assertStringContainsString('id="requiredCaseFilters"', $content);
        
        // Verify the structure around the required case ID field
        $pattern = '/id="requiredCaseFilters"[^>]*>.*?id="req_case_id".*?name="case_id"/s';
        $this->assertMatchesRegularExpression($pattern, $content);
    }
}