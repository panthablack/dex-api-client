<?php

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Models\DataMigration;

class VerificationStatusService
{
    /**
     * Calculate verification status for a migration by directly querying the migrated records
     */
    public function getVerificationStatus(DataMigration $migration): array
    {
        $status = 'idle';
        $totalRecords = 0;
        $verifiedRecords = 0;
        $failedRecords = 0;
        $pendingRecords = 0;
        $resourceProgress = [];

        // Check each resource type
        foreach ($migration->resource_types as $resourceType) {
            $stats = $this->getResourceVerificationStats($migration, $resourceType);

            $totalRecords += $stats['total'];
            $verifiedRecords += $stats['verified'];
            $failedRecords += $stats['failed'];
            $pendingRecords += $stats['pending'];

            $resourceProgress[$resourceType] = [
                'total' => $stats['total'],
                'verified' => $stats['verified'],
                'failed' => $stats['failed'],
                'pending' => $stats['pending'],
                'processed' => $stats['verified'] + $stats['failed']
            ];
        }

        // Determine overall verification status
        $processedRecords = $verifiedRecords + $failedRecords;

        if ($totalRecords === 0) {
            $status = 'no_data';
            $message = 'No data available for verification';
        } elseif ($processedRecords === 0) {
            $status = 'idle';
            $message = 'Verification not started';
        } elseif ($processedRecords === $totalRecords) {
            if ($failedRecords === 0) {
                $status = 'completed';
                $message = 'All records verified successfully';
            } else {
                $status = 'completed_with_failures';
                $message = "Verification completed: {$verifiedRecords} verified, {$failedRecords} failed";
            }
        } else {
            $status = 'partial';
            $message = "Partial verification: {$processedRecords} of {$totalRecords} processed";
        }

        // Determine what actions are available
        $availableActions = $this->getAvailableActions($status, $pendingRecords, $failedRecords);

        return [
            'status' => $status,
            'message' => $message,
            'total_records' => $totalRecords,
            'verified_records' => $verifiedRecords,
            'failed_records' => $failedRecords,
            'pending_records' => $pendingRecords,
            'processed_records' => $processedRecords,
            'progress_percentage' => $totalRecords > 0 ? round(($processedRecords / $totalRecords) * 100, 2) : 0,
            'success_rate' => $processedRecords > 0 ? round(($verifiedRecords / $processedRecords) * 100, 2) : 0,
            'resource_progress' => $resourceProgress,
            'available_actions' => $availableActions
        ];
    }

    /**
     * Get verification stats for a specific resource type
     */
    private function getResourceVerificationStats(DataMigration $migration, string $resourceType): array
    {
        $relationMethod = $this->getRelationMethod($resourceType);

        if (!$relationMethod) {
            return ['total' => 0, 'verified' => 0, 'failed' => 0, 'pending' => 0];
        }

        // Use separate queries to avoid query reuse issues
        $total = $migration->$relationMethod()->count();
        $verified = $migration->$relationMethod()->where('verification_status', VerificationStatus::VERIFIED)->count();
        $failed = $migration->$relationMethod()->where('verification_status', VerificationStatus::FAILED)->count();
        $pending = $total - $verified - $failed;

        return [
            'total' => $total,
            'verified' => $verified,
            'failed' => $failed,
            'pending' => $pending
        ];
    }

    /**
     * Get the relation method name for a resource type
     */
    private function getRelationMethod(string $resourceType): ?string
    {
        return match ($resourceType) {
            'clients' => 'clients',
            'cases' => 'cases',
            'sessions' => 'sessions',
            default => null
        };
    }

    /**
     * Determine what verification actions are available based on current state
     */
    private function getAvailableActions(string $status, int $pendingRecords, int $failedRecords): array
    {
        $actions = [];

        // Quick verify is always available if there's data
        if (in_array($status, ['idle', 'partial', 'completed', 'completed_with_failures'])) {
            $actions[] = 'quick_verify';
        }

        // Full verify available for idle or completed states
        if (in_array($status, ['idle', 'completed', 'completed_with_failures'])) {
            $actions[] = 'full_verify';
        }

        // Continue verify available when there are failed or pending records
        if ($failedRecords > 0 || $pendingRecords > 0) {
            $actions[] = 'continue_verify';
        }

        return $actions;
    }

    /**
     * Reset verification status for all records in a migration
     */
    public function resetVerificationStatus(DataMigration $migration): void
    {
        foreach ($migration->resource_types as $resourceType) {
            $relationMethod = $this->getRelationMethod($resourceType);

            if ($relationMethod) {
                $migration->$relationMethod()->update([
                    'verification_status' => VerificationStatus::PENDING,
                    'verified_at' => null,
                    'verification_error' => null
                ]);
            }
        }
    }

    /**
     * Check if verification can be started for a migration
     */
    public function canStartVerification(DataMigration $migration): bool
    {
        // Can verify if migration is completed or failed and has records
        if (!in_array($migration->status, ['completed', 'failed'])) {
            return false;
        }

        // Check if there are any completed batches with records
        foreach ($migration->resource_types as $resourceType) {
            $relationMethod = $this->getRelationMethod($resourceType);

            if ($relationMethod && $migration->$relationMethod()->count() > 0) {
                return true;
            }
        }

        return false;
    }
}