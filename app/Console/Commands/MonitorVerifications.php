<?php

namespace App\Console\Commands;

use App\Models\DataMigration;
use App\Enums\VerificationStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MonitorVerifications extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'verification:monitor
                            {--auto-fix : Automatically restart stalled verifications}
                            {--max-age=3600 : Maximum age in seconds before considering verification stalled}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor verification jobs for stalls and incomplete states - PREVENTION MEASURE';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $autoFix = $this->option('auto-fix');
        $maxAge = (int) $this->option('max-age');

        $this->info('Monitoring verification jobs...');

        // Check for migrations with incomplete verifications
        $incompleteVerifications = $this->findIncompleteVerifications();

        if ($incompleteVerifications->isEmpty()) {
            $this->info('No incomplete verifications found.');
            return 0;
        }

        $this->warn("Found {$incompleteVerifications->count()} migrations with incomplete verifications:");

        foreach ($incompleteVerifications as $migration) {
            $stats = $this->getVerificationStats($migration);

            $this->table(
                ['Migration', 'Total', 'Verified', 'Failed', 'Pending', 'Completion %'],
                [[
                    $migration->id . ': ' . $migration->name,
                    $stats['total'],
                    $stats['verified'],
                    $stats['failed'],
                    $stats['pending'],
                    $stats['completion_percentage'] . '%'
                ]]
            );

            // Check for stalled verification jobs
            $stalledJob = $this->checkForStalledJob($migration, $maxAge);

            if ($stalledJob) {
                $this->error("  ⚠️  Verification job appears stalled (no activity for {$stalledJob['age']} seconds)");

                if ($autoFix) {
                    $this->info("  🔧 Auto-fixing: Restarting verification...");
                    $this->restartVerification($migration);
                } else {
                    $this->warn("  💡 Run with --auto-fix to automatically restart stalled verifications");
                }
            } else {
                $this->warn("  ⏸️  Verification incomplete but no active job found");

                if ($autoFix) {
                    $this->info("  🔧 Auto-fixing: Starting continue verification...");
                    $this->continueVerification($migration);
                } else {
                    $this->warn("  💡 Run with --auto-fix to automatically continue incomplete verifications");
                }
            }
        }

        return 0;
    }

    /**
     * Find migrations with incomplete verifications
     */
    protected function findIncompleteVerifications()
    {
        return DataMigration::whereIn('status', ['completed', 'failed'])
            ->with('batches')
            ->get()
            ->filter(function ($migration) {
                $stats = $this->getVerificationStats($migration);
                return $stats['pending'] > 0;
            });
    }

    /**
     * Get verification statistics for a migration
     */
    protected function getVerificationStats(DataMigration $migration)
    {
        $totalRecords = 0;
        $verifiedRecords = 0;
        $failedRecords = 0;
        $pendingRecords = 0;

        foreach ($migration->resource_types as $resourceType) {
            $modelClass = $this->getModelClass($resourceType);
            if ($modelClass) {
                $batchIds = $migration->batches()
                    ->where('resource_type', $resourceType)
                    ->where('status', 'completed')
                    ->pluck('batch_id')
                    ->toArray();

                if (!empty($batchIds)) {
                    $total = $modelClass::whereIn('migration_batch_id', $batchIds)->count();
                    $verified = $modelClass::whereIn('migration_batch_id', $batchIds)
                        ->where('verification_status', VerificationStatus::VERIFIED)
                        ->count();
                    $failed = $modelClass::whereIn('migration_batch_id', $batchIds)
                        ->where('verification_status', VerificationStatus::FAILED)
                        ->count();
                    $pending = $modelClass::whereIn('migration_batch_id', $batchIds)
                        ->where('verification_status', VerificationStatus::PENDING)
                        ->count();

                    $totalRecords += $total;
                    $verifiedRecords += $verified;
                    $failedRecords += $failed;
                    $pendingRecords += $pending;
                }
            }
        }

        return [
            'total' => $totalRecords,
            'verified' => $verifiedRecords,
            'failed' => $failedRecords,
            'pending' => $pendingRecords,
            'completion_percentage' => $totalRecords > 0 ? round((($verifiedRecords + $failedRecords) / $totalRecords) * 100, 1) : 0
        ];
    }

    /**
     * Check for stalled verification job
     */
    protected function checkForStalledJob(DataMigration $migration, int $maxAge)
    {
        // Check recent verification IDs pattern
        $currentTime = time();
        $checkPatterns = [
            $migration->id . '_' . ($currentTime - 7200), // Last 2 hours
            $migration->id . '_continue_' . ($currentTime - 7200),
        ];

        // Add some recent timestamps to check
        for ($i = 0; $i < 20; $i++) {
            $timestamp = $currentTime - ($i * 300); // Every 5 minutes for last 100 minutes
            $checkPatterns[] = $migration->id . '_' . $timestamp;
            $checkPatterns[] = $migration->id . '_continue_' . $timestamp;
        }

        foreach ($checkPatterns as $pattern) {
            $heartbeatKey = "verification_heartbeat_{$pattern}";
            $heartbeat = Cache::get($heartbeatKey);

            if ($heartbeat && isset($heartbeat['timestamp'])) {
                $age = $currentTime - $heartbeat['timestamp'];
                if ($age > $maxAge) {
                    return [
                        'verification_id' => $pattern,
                        'age' => $age,
                        'heartbeat' => $heartbeat
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Restart a stalled verification
     */
    protected function restartVerification(DataMigration $migration)
    {
        $verificationId = $migration->id . '_monitor_restart_' . time();

        try {
            \App\Jobs\VerifyMigrationJob::dispatch($migration, $verificationId, true);
            $this->info("  ✅ Restarted verification with ID: {$verificationId}");

            Log::info('Verification monitoring: Restarted stalled verification', [
                'migration_id' => $migration->id,
                'new_verification_id' => $verificationId,
                'action' => 'auto_restart'
            ]);
        } catch (\Exception $e) {
            $this->error("  ❌ Failed to restart verification: " . $e->getMessage());

            Log::error('Verification monitoring: Failed to restart verification', [
                'migration_id' => $migration->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Continue an incomplete verification
     */
    protected function continueVerification(DataMigration $migration)
    {
        $verificationId = $migration->id . '_monitor_continue_' . time();

        try {
            \App\Jobs\VerifyMigrationJob::dispatch($migration, $verificationId, true);
            $this->info("  ✅ Started continue verification with ID: {$verificationId}");

            Log::info('Verification monitoring: Started continue verification', [
                'migration_id' => $migration->id,
                'new_verification_id' => $verificationId,
                'action' => 'auto_continue'
            ]);
        } catch (\Exception $e) {
            $this->error("  ❌ Failed to continue verification: " . $e->getMessage());

            Log::error('Verification monitoring: Failed to continue verification', [
                'migration_id' => $migration->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the model class for a resource type
     */
    protected function getModelClass(string $resourceType): ?string
    {
        return match ($resourceType) {
            'clients' => \App\Models\MigratedClient::class,
            'cases' => \App\Models\MigratedCase::class,
            'sessions' => \App\Models\MigratedSession::class,
            default => null
        };
    }
}