<?php

namespace Tests\Feature;

use App\Resources\Filters;
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
     * Test fetchFullCaseData with single case
     */
    public function test_fetch_full_case_data_with_single_case()
    {
        // Mock the SOAP client
        $mockSoapClient = Mockery::mock(SoapClientService::class);

        // Mock SearchCase call (returns single case)
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
                    'Status' => 'Open',
                    'ClientId' => 'CLIENT789',
                    'DetailedInfo' => 'Full details for single case'
                ]
            ]);

        // Create service with mock
        $service = new DataExchangeService($mockSoapClient);

        // Test fetchFullCaseData
        $result = $service->fetchFullCaseData(new Filters(['case_id' => 'SINGLE001']));

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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
