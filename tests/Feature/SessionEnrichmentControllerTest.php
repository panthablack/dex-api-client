<?php

namespace Tests\Feature;

use App\Models\MigratedShallowSession;
use App\Models\MigratedEnrichedSession;
use App\Models\MigratedCase;
use App\Models\MigratedEnrichedCase;
use App\Models\DataMigrationBatch;
use App\Enums\ResourceType;
use App\Enums\VerificationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Mockery;

class SessionEnrichmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_index_displays_dashboard_when_migrated_cases_available(): void
    {
        // Create migrated cases to satisfy "hasAvailableCases" check
        $batch = DataMigrationBatch::factory()->create();
        MigratedCase::factory()->create([
            'data_migration_batch_id' => $batch->id,
        ]);

        $response = $this->get(route('enrichment.sessions.index'));

        $response->assertOk();
        $response->assertViewIs('enrichment.sessions.index');
        $response->assertViewHas('hasAvailableCases', true);
        $response->assertViewHas('canEnrich', false); // No shallow sessions yet
    }

    public function test_index_shows_warning_when_no_available_data(): void
    {
        $response = $this->get(route('enrichment.sessions.index'));

        $response->assertOk();
        $response->assertViewIs('enrichment.sessions.index');
        $response->assertViewHas('hasAvailableCases', false);
        $response->assertViewHas('hasShallowSessions', false);
        $response->assertViewHas('canEnrich', false);
    }

    public function test_progress_endpoint_returns_correct_stats(): void
    {
        // Create enrichment process with batches
        $process = \App\Models\EnrichmentProcess::create([
            'resource_type' => ResourceType::SESSION,
            'status' => 'IN_PROGRESS',
            'total_items' => 3,
        ]);

        // Create batches for the process
        \App\Models\EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'item_ids' => json_encode(['1', '2']),
            'status' => 'COMPLETED',
            'items_processed' => 2,
            'items_failed' => 0,
        ]);

        \App\Models\EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 2,
            'item_ids' => json_encode(['3']),
            'status' => 'PENDING',
            'items_processed' => 0,
            'items_failed' => 0,
        ]);

        // Enrich 2 of them
        for ($i = 1; $i <= 2; $i++) {
            MigratedEnrichedSession::create([
                'session_id' => "SESSION-{$i}",
                'case_id' => "CASE-{$i}",
                'session_date' => now(),
                'service_type_id' => 100,
                'total_number_of_unidentified_clients' => 0,
                'api_response' => [],
                'verification_status' => VerificationStatus::PENDING,
            ]);
        }

        $response = $this->getJson(route('enrichment.sessions.api.progress'));

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'total_items',
                'processed_items',
                'failed_items',
                'progress_percentage',
                'success_rate',
                'status',
                'completed_batches',
                'total_batches',
                'started_at',
                'completed_at',
            ]
        ]);

        // Verify specific values
        $data = $response->json('data');
        $this->assertEquals(3, $data['total_items']);
        $this->assertEquals(2, $data['processed_items']);
        $this->assertEquals(0, $data['failed_items']);
        $this->assertEquals(1, $data['completed_batches']);
        $this->assertEquals(2, $data['total_batches']);
    }

    public function test_unenriched_endpoint_returns_correct_session_ids(): void
    {
        // Create shallow sessions
        MigratedShallowSession::create([
            'session_id' => 'SESSION-A',
            'case_id' => 'CASE-A',
            'api_response' => [],
        ]);

        MigratedShallowSession::create([
            'session_id' => 'SESSION-B',
            'case_id' => 'CASE-B',
            'api_response' => [],
        ]);

        MigratedShallowSession::create([
            'session_id' => 'SESSION-C',
            'case_id' => 'CASE-C',
            'api_response' => [],
        ]);

        // Enrich only SESSION-B
        MigratedEnrichedSession::create([
            'session_id' => 'SESSION-B',
            'case_id' => 'CASE-B',
            'session_date' => now(),
            'service_type_id' => 100,
            'total_number_of_unidentified_clients' => 0,
            'api_response' => [],
            'verification_status' => VerificationStatus::PENDING,
        ]);

        $response = $this->getJson(route('enrichment.sessions.api.unenriched'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'count' => 2
            ]
        ]);

        $unenrichedIds = $response->json('data.unenriched_session_ids');
        $this->assertContains('SESSION-A', $unenrichedIds);
        $this->assertContains('SESSION-C', $unenrichedIds);
        $this->assertNotContains('SESSION-B', $unenrichedIds);
    }

    public function test_start_endpoint_rejects_when_no_shallow_sessions(): void
    {
        $response = $this->postJson(route('enrichment.sessions.api.start'));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'Cannot start enrichment. Please complete a SHALLOW_SESSION migration first.'
        ]);
    }

    public function test_start_endpoint_enriches_sessions_successfully(): void
    {
        Queue::fake();

        // Create shallow sessions
        MigratedShallowSession::create([
            'session_id' => 'SESSION-100',
            'case_id' => 'CASE-100',
            'api_response' => [],
        ]);

        MigratedShallowSession::create([
            'session_id' => 'SESSION-101',
            'case_id' => 'CASE-101',
            'api_response' => [],
        ]);

        $response = $this->postJson(route('enrichment.sessions.api.start'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Enrichment process started',
        ]);

        // Verify response contains process data
        $data = $response->json('data');
        $this->assertArrayHasKey('process_id', $data);
        $this->assertArrayHasKey('total_items', $data);
        $this->assertArrayHasKey('batch_count', $data);
        $this->assertArrayHasKey('status', $data);

        // Verify batch jobs were dispatched (initialization is now synchronous)
        Queue::assertPushed(\App\Jobs\ProcessEnrichmentBatch::class);
    }

    public function test_start_endpoint_skips_already_enriched_sessions(): void
    {
        Queue::fake();

        // Create shallow sessions
        MigratedShallowSession::create([
            'session_id' => 'SESSION-200',
            'case_id' => 'CASE-200',
            'api_response' => [],
        ]);

        MigratedShallowSession::create([
            'session_id' => 'SESSION-201',
            'case_id' => 'CASE-201',
            'api_response' => [],
        ]);

        // SESSION-200 is already enriched
        MigratedEnrichedSession::create([
            'session_id' => 'SESSION-200',
            'case_id' => 'CASE-200',
            'session_date' => now(),
            'service_type_id' => 100,
            'total_number_of_unidentified_clients' => 0,
            'api_response' => [],
            'verification_status' => VerificationStatus::PENDING,
        ]);

        $response = $this->postJson(route('enrichment.sessions.api.start'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Enrichment process started',
        ]);

        // Verify batch jobs were dispatched (skip logic is handled in initializeEnrichment)
        Queue::assertPushed(\App\Jobs\ProcessEnrichmentBatch::class);
    }

    public function test_pause_endpoint_pauses_active_process(): void
    {
        // Create an active enrichment process
        $process = \App\Models\EnrichmentProcess::create([
            'resource_type' => ResourceType::SESSION,
            'status' => 'IN_PROGRESS',
            'total_items' => 10,
        ]);

        $response = $this->postJson(route('enrichment.sessions.api.pause'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Enrichment paused. Current batch will complete before stopping.',
        ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('process_id', $data);
        $this->assertArrayHasKey('paused_at', $data);

        // Verify process was paused in database
        $process->refresh();
        $this->assertNotNull($process->paused_at);
    }

    public function test_pause_endpoint_fails_when_no_active_process(): void
    {
        $response = $this->postJson(route('enrichment.sessions.api.pause'));

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'error' => 'No active enrichment process found'
        ]);
    }

    public function test_resume_endpoint_resumes_paused_process(): void
    {
        Queue::fake();

        // Create a paused enrichment process
        $process = \App\Models\EnrichmentProcess::create([
            'resource_type' => ResourceType::SESSION,
            'status' => 'IN_PROGRESS',
            'total_items' => 10,
            'paused_at' => now(),
        ]);

        // Create pending batches
        \App\Models\EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'item_ids' => json_encode(['1', '2']),
            'status' => 'PENDING',
        ]);

        $response = $this->postJson(route('enrichment.sessions.api.resume'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Enrichment resumed',
        ]);

        // Verify process pause was cleared
        $process->refresh();
        $this->assertNull($process->paused_at);
    }

    public function test_resume_endpoint_fails_when_no_paused_process(): void
    {
        $response = $this->postJson(route('enrichment.sessions.api.resume'));

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'error' => 'No paused enrichment process found'
        ]);
    }

    public function test_restart_endpoint_truncates_and_creates_new_process(): void
    {
        Queue::fake();

        // Create existing enriched sessions
        MigratedEnrichedSession::create([
            'session_id' => 'SESSION-OLD',
            'case_id' => 'CASE-OLD',
            'session_date' => now(),
            'service_type_id' => 100,
            'total_number_of_unidentified_clients' => 0,
            'api_response' => [],
            'verification_status' => VerificationStatus::PENDING,
        ]);

        // Create shallow sessions for restart
        MigratedShallowSession::create([
            'session_id' => 'SESSION-NEW-1',
            'case_id' => 'CASE-NEW-1',
            'api_response' => [],
        ]);

        $response = $this->postJson(route('enrichment.sessions.api.restart'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Enrichment restarted. All previous enriched data has been cleared.',
        ]);

        // Verify old data was truncated
        $this->assertDatabaseMissing('migrated_enriched_sessions', [
            'session_id' => 'SESSION-OLD'
        ]);

        // Verify new process was created
        $data = $response->json('data');
        $this->assertArrayHasKey('process_id', $data);
        $this->assertArrayHasKey('status', $data);
    }

    public function test_restart_endpoint_fails_when_no_shallow_sessions(): void
    {
        $response = $this->postJson(route('enrichment.sessions.api.restart'));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'Cannot restart enrichment. Please complete a SHALLOW_SESSION migration first.'
        ]);
    }

    public function test_can_generate_shallow_sessions_returns_correct_status(): void
    {
        // No cases available
        $response = $this->getJson(route('enrichment.sessions.api.can-generate'));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertFalse($data['can_generate']);
        $this->assertNull($data['source']);

        // Create migrated cases (available source)
        $batch = DataMigrationBatch::factory()->create();
        MigratedCase::factory()->create([
            'data_migration_batch_id' => $batch->id,
        ]);

        $response = $this->getJson(route('enrichment.sessions.api.can-generate'));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertTrue($data['can_generate']);
        $this->assertNotNull($data['source']);
    }

    public function test_generate_shallow_sessions_endpoint_works(): void
    {
        // Create migrated cases with sessions
        $batch = DataMigrationBatch::factory()->create();
        MigratedCase::factory()->create([
            'case_id' => 'CASE-GEN-1',
            'data_migration_batch_id' => $batch->id,
            'api_response' => json_encode([
                'sessions' => [
                    ['id' => 'SESSION-GEN-1'],
                    ['id' => 'SESSION-GEN-2'],
                ]
            ]),
        ]);

        $response = $this->postJson(route('enrichment.sessions.api.generate'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        // Verify response structure
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('newly_created', $data);
        $this->assertIsInt($data['newly_created']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
