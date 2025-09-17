<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationSession extends Model
{
    protected $fillable = [
        'migration_id',
        'type',
        'status',
        'current_activity',
        'total_records',
        'processed_records',
        'verified_records',
        'failed_records',
        'resource_progress',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'resource_progress' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_records' => 'integer',
        'processed_records' => 'integer',
        'verified_records' => 'integer',
        'failed_records' => 'integer',
    ];

    public function migration(): BelongsTo
    {
        return $this->belongsTo(DataMigration::class);
    }

    /**
     * Get the progress percentage
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->total_records === 0) {
            return 0;
        }

        return min(100, (int) round(($this->processed_records / $this->total_records) * 100));
    }

    /**
     * Get the success rate percentage
     */
    public function getSuccessRateAttribute(): int
    {
        if ($this->processed_records === 0) {
            return 0;
        }

        return (int) round(($this->verified_records / $this->processed_records) * 100);
    }

    /**
     * Check if verification is currently active
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['starting', 'in_progress', 'stopping']);
    }

    /**
     * Check if verification is completed (success or failure)
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'stopped']);
    }

    /**
     * Update progress stats
     */
    public function updateProgress(int $total, int $processed, int $verified, int $failed, array $resourceProgress = null): void
    {
        $this->update([
            'total_records' => $total,
            'processed_records' => $processed,
            'verified_records' => $verified,
            'failed_records' => $failed,
            'resource_progress' => $resourceProgress ?: $this->resource_progress,
        ]);
    }

    /**
     * Mark verification as completed
     */
    public function markCompleted(string $status = 'completed', string $activity = null): void
    {
        $this->update([
            'status' => $status,
            'current_activity' => $activity,
            'completed_at' => now(),
        ]);
    }

    /**
     * Update current activity
     */
    public function updateActivity(string $activity): void
    {
        $this->update([
            'current_activity' => $activity,
        ]);
    }
}
