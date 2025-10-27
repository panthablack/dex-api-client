<?php

namespace App\Models;

use App\Enums\ResourceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class EnrichmentProcess extends Model
{
    use HasFactory;

    protected $fillable = [
        'resource_type',
        'status',
        'total_items',
        'started_at',
        'completed_at',
        'paused_at',
    ];

    protected $casts = [
        'resource_type' => ResourceType::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'paused_at' => 'datetime',
    ];

    /**
     * Get all batches for this enrichment process
     */
    public function batches(): HasMany
    {
        return $this->hasMany(EnrichmentBatch::class);
    }

    /**
     * Get incomplete batches (not COMPLETED or FAILED)
     */
    public function incompleteBatches(): Collection
    {
        return $this->batches->filter(
            fn(EnrichmentBatch $batch) => !in_array($batch->status, ['COMPLETED', 'FAILED'])
        );
    }

    /**
     * Get failed batches
     */
    public function failedBatches(): Collection
    {
        return $this->batches->filter(
            fn(EnrichmentBatch $batch) => $batch->status === 'FAILED'
        );
    }

    /**
     * Get pending batches
     */
    public function pendingBatches(): Collection
    {
        return $this->batches->filter(
            fn(EnrichmentBatch $batch) => $batch->status === 'PENDING'
        );
    }

    /**
     * Get the total number of processed items across all batches
     */
    public function getProcessedItemsAttribute(): int
    {
        return $this->batches->sum('items_processed') ?? 0;
    }

    /**
     * Get the progress percentage based on processed vs total items
     */
    public function getProgressPercentageAttribute(): float
    {
        if (!$this->total_items || $this->total_items == 0) {
            return 0.0;
        }

        $processed = $this->processed_items;
        $percentage = ($processed / $this->total_items) * 100;

        return round($percentage, 2);
    }

    /**
     * Get the total number of failed items across all batches
     */
    public function getFailedItemsAttribute(): int
    {
        return $this->batches->sum('items_failed') ?? 0;
    }

    /**
     * Get the success rate percentage based on processed vs failed
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->total_items == 0) {
            return 0.0;
        }

        $failed = $this->failed_items;
        $attempted = $this->processed_items + $failed;

        if ($attempted == 0) {
            return 0.0;
        }

        $percentage = ($this->processed_items / $attempted) * 100;

        return round($percentage, 2);
    }

    /**
     * Scope to only include active enrichments
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['PENDING', 'IN_PROGRESS']);
    }

    /**
     * Scope to only include completed enrichments
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'COMPLETED');
    }

    /**
     * Scope to only include failed enrichments
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'FAILED');
    }

    /**
     * Handle enrichment process failure
     */
    public function onFail(\Throwable $e): void
    {
        $this->update([
            'status' => 'FAILED',
            'completed_at' => now()
        ]);
    }
}
