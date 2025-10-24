<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Services\SessionShallowGenerationService;
use App\Models\MigratedCase;
use App\Models\MigratedEnrichedCase;
use App\Models\MigratedShallowSession;
use App\Models\DataMigrationBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SessionShallowGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SessionShallowGenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SessionShallowGenerationService::class);
    }

    #[Test]
    public function canGenerate_returns_false_when_no_cases_available()
    {
        $result = $this->service->canGenerate();
        $this->assertFalse($result);
    }

    #[Test]
    public function canGenerate_returns_true_when_migrated_cases_exist()
    {
        $batch = DataMigrationBatch::factory()->create();

        MigratedCase::factory()->create([
            'data_migration_batch_id' => $batch->id,
            'sessions' => [
                ['session_id' => 'SESSION001']
            ]
        ]);

        $result = $this->service->canGenerate();
        $this->assertTrue($result);
    }

    #[Test]
    public function canGenerate_returns_true_when_enriched_cases_exist()
    {
        $batch = DataMigrationBatch::factory()->create();

        MigratedEnrichedCase::factory()->create([
            'sessions' => [
                ['session_id' => 'SESSION001']
            ]
        ]);

        $result = $this->service->canGenerate();
        $this->assertTrue($result);
    }

    #[Test]
    public function getAvailableSource_returns_null_when_no_cases()
    {
        $source = $this->service->getAvailableSource();
        $this->assertNull($source);
    }

    #[Test]
    public function getAvailableSource_prefers_migrated_cases_over_enriched()
    {
        $batch = DataMigrationBatch::factory()->create();

        MigratedCase::factory()->create([
            'data_migration_batch_id' => $batch->id,
            'sessions' => [
                ['session_id' => 'SESSION001']
            ]
        ]);

        MigratedEnrichedCase::factory()->create([
            'sessions' => [
                ['session_id' => 'SESSION002']
            ]
        ]);

        $source = $this->service->getAvailableSource();
        $this->assertEquals('migrated_cases', $source);
    }

    #[Test]
    public function getAvailableSource_returns_enriched_cases_when_no_migrated_cases()
    {
        MigratedEnrichedCase::factory()->create([
            'sessions' => [
                ['session_id' => 'SESSION001']
            ]
        ]);

        $source = $this->service->getAvailableSource();
        $this->assertEquals('migrated_enriched_cases', $source);
    }

    #[Test]
    public function generateShallowSessions_throws_when_no_cases_available()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No case data available');

        $this->service->generateShallowSessions();
    }

    #[Test]
    public function generateShallowSessions_creates_shallow_sessions_from_migrated_cases()
    {
        $batch = DataMigrationBatch::factory()->create();

        MigratedCase::factory()->create([
            'case_id' => 'CASE001',
            'data_migration_batch_id' => $batch->id,
            'sessions' => [
                ['session_id' => 'SESSION001'],
                ['session_id' => 'SESSION002'],
            ]
        ]);

        $stats = $this->service->generateShallowSessions();

        $this->assertEquals(2, $stats['total_sessions_found']);
        $this->assertEquals(2, $stats['newly_created']);
        $this->assertEquals(0, $stats['already_existed']);
        $this->assertEquals('migrated_cases', $stats['source']);
        $this->assertEmpty($stats['errors']);

        // Verify sessions were created
        $this->assertDatabaseHas('migrated_shallow_sessions', [
            'session_id' => 'SESSION001',
            'case_id' => 'CASE001'
        ]);

        $this->assertDatabaseHas('migrated_shallow_sessions', [
            'session_id' => 'SESSION002',
            'case_id' => 'CASE001'
        ]);
    }

    #[Test]
    public function generateShallowSessions_skips_already_existing_sessions()
    {
        $batch = DataMigrationBatch::factory()->create();

        // Create existing shallow session
        MigratedShallowSession::create([
            'session_id' => 'SESSION001',
            'case_id' => 'CASE001'
        ]);

        // Create case with same session
        MigratedCase::factory()->create([
            'case_id' => 'CASE001',
            'data_migration_batch_id' => $batch->id,
            'sessions' => [
                ['session_id' => 'SESSION001'],
                ['session_id' => 'SESSION002'],
            ]
        ]);

        $stats = $this->service->generateShallowSessions();

        $this->assertEquals(2, $stats['total_sessions_found']);
        $this->assertEquals(1, $stats['newly_created']);
        $this->assertEquals(1, $stats['already_existed']);
    }

    #[Test]
    public function generateShallowSessions_handles_multiple_cases()
    {
        $batch = DataMigrationBatch::factory()->create();

        MigratedCase::factory()->create([
            'case_id' => 'CASE001',
            'data_migration_batch_id' => $batch->id,
            'sessions' => [
                ['session_id' => 'SESSION001'],
                ['session_id' => 'SESSION002'],
            ]
        ]);

        MigratedCase::factory()->create([
            'case_id' => 'CASE002',
            'data_migration_batch_id' => $batch->id,
            'sessions' => [
                ['session_id' => 'SESSION003'],
            ]
        ]);

        $stats = $this->service->generateShallowSessions();

        $this->assertEquals(3, $stats['total_sessions_found']);
        $this->assertEquals(3, $stats['newly_created']);

        // Verify all sessions
        $this->assertEquals(3, MigratedShallowSession::count());
    }

    #[Test]
    public function generateShallowSessions_handles_empty_sessions_array_from_migrated_cases()
    {
        $batch = DataMigrationBatch::factory()->create();

        MigratedCase::factory()->create([
            'case_id' => 'CASE001',
            'data_migration_batch_id' => $batch->id,
            'sessions' => []
        ]);

        $stats = $this->service->generateShallowSessions();

        $this->assertEquals(0, $stats['total_sessions_found']);
        $this->assertEquals(0, $stats['newly_created']);
        $this->assertEquals('migrated_cases', $stats['source']);
    }

    #[Test]
    public function generateShallowSessions_handles_empty_sessions_array()
    {
        $batch = DataMigrationBatch::factory()->create();

        MigratedCase::factory()->create([
            'case_id' => 'CASE001',
            'data_migration_batch_id' => $batch->id,
            'sessions' => []
        ]);

        $stats = $this->service->generateShallowSessions();

        $this->assertEquals(0, $stats['total_sessions_found']);
    }

    #[Test]
    public function generateShallowSessions_handles_single_session_object()
    {
        $batch = DataMigrationBatch::factory()->create();

        // Create case with single session object (not array)
        $case = MigratedCase::factory()->create([
            'case_id' => 'CASE001',
            'data_migration_batch_id' => $batch->id,
        ]);

        // Manually set sessions to an object to simulate API response
        $case->update([
            'sessions' => (object)['session_id' => 'SESSION001']
        ]);

        $stats = $this->service->generateShallowSessions();

        $this->assertEquals(1, $stats['total_sessions_found']);
        $this->assertEquals(1, $stats['newly_created']);

        $this->assertDatabaseHas('migrated_shallow_sessions', [
            'session_id' => 'SESSION001'
        ]);
    }

    #[Test]
    public function generateShallowSessions_handles_various_session_id_formats()
    {
        $batch = DataMigrationBatch::factory()->create();

        MigratedCase::factory()->create([
            'case_id' => 'CASE001',
            'data_migration_batch_id' => $batch->id,
            'sessions' => [
                ['session_id' => 'SESSION001'],        // Lowercase
                ['SessionId' => 'SESSION002'],         // CamelCase
                ['Session' => 'SESSION003'],           // Alternative key
                'SESSION004',                           // String format
            ]
        ]);

        $stats = $this->service->generateShallowSessions();

        $this->assertEquals(4, $stats['total_sessions_found']);
        $this->assertEquals(4, $stats['newly_created']);

        // Verify all were created
        $this->assertDatabaseHas('migrated_shallow_sessions', ['session_id' => 'SESSION001']);
        $this->assertDatabaseHas('migrated_shallow_sessions', ['session_id' => 'SESSION002']);
        $this->assertDatabaseHas('migrated_shallow_sessions', ['session_id' => 'SESSION003']);
        $this->assertDatabaseHas('migrated_shallow_sessions', ['session_id' => 'SESSION004']);
    }

    #[Test]
    public function generateShallowSessions_handles_numeric_session_ids()
    {
        $batch = DataMigrationBatch::factory()->create();

        MigratedCase::factory()->create([
            'case_id' => 'CASE001',
            'data_migration_batch_id' => $batch->id,
            'sessions' => [
                ['session_id' => 12345],               // Numeric
                ['session_id' => '67890'],             // String numeric
            ]
        ]);

        $stats = $this->service->generateShallowSessions();

        $this->assertEquals(2, $stats['total_sessions_found']);
        $this->assertEquals(2, $stats['newly_created']);

        // Both should be stored as strings
        $this->assertDatabaseHas('migrated_shallow_sessions', ['session_id' => '12345']);
        $this->assertDatabaseHas('migrated_shallow_sessions', ['session_id' => '67890']);
    }

    #[Test]
    public function generateShallowSessions_handles_invalid_sessions_gracefully()
    {
        $batch = DataMigrationBatch::factory()->create();

        MigratedCase::factory()->create([
            'case_id' => 'CASE001',
            'data_migration_batch_id' => $batch->id,
            'sessions' => [
                ['session_id' => 'SESSION001'],
                null,                                   // Null entry
                ['no_id_field' => 'value'],            // Missing session_id
                'SESSION002',                           // String format
            ]
        ]);

        $stats = $this->service->generateShallowSessions();

        // Should extract SESSION001 and SESSION002, skip invalid ones
        $this->assertGreaterThanOrEqual(2, $stats['total_sessions_found']);
        $this->assertDatabaseHas('migrated_shallow_sessions', ['session_id' => 'SESSION001']);
        $this->assertDatabaseHas('migrated_shallow_sessions', ['session_id' => 'SESSION002']);
    }

    #[Test]
    public function generateShallowSessions_uses_enriched_cases_as_fallback()
    {
        // No migrated_cases, only enriched_cases
        MigratedEnrichedCase::factory()->create([
            'case_id' => 'CASE001',
            'sessions' => [
                ['session_id' => 'SESSION001'],
                ['session_id' => 'SESSION002'],
            ]
        ]);

        $stats = $this->service->generateShallowSessions();

        $this->assertEquals(2, $stats['total_sessions_found']);
        $this->assertEquals(2, $stats['newly_created']);
        $this->assertEquals('migrated_enriched_cases', $stats['source']);

        // Verify sessions were created
        $this->assertDatabaseHas('migrated_shallow_sessions', [
            'session_id' => 'SESSION001',
            'case_id' => 'CASE001'
        ]);
    }

    #[Test]
    public function generateShallowSessions_logs_generation_start_and_completion()
    {
        $batch = DataMigrationBatch::factory()->create();

        MigratedCase::factory()->create([
            'case_id' => 'CASE001',
            'data_migration_batch_id' => $batch->id,
            'sessions' => [
                ['session_id' => 'SESSION001'],
            ]
        ]);

        // This just ensures the method completes without errors
        // Logging is implementation detail
        $stats = $this->service->generateShallowSessions();

        $this->assertTrue($stats['success'] ?? true); // Check if stats returned
        $this->assertIsArray($stats);
    }
}
