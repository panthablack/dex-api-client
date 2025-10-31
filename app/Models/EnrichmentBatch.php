<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class EnrichmentBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrichment_process_id',
        'batch_number',
        'status',
        'item_ids',
        'batch_size',
        'items_processed',
        'items_failed',
        'items_skipped',
        'failed_item_ids',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'item_ids' => 'array',
        'failed_item_ids' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the enrichment process this batch belongs to
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(EnrichmentProcess::class, 'enrichment_process_id');
    }

    /**
     * Check if batch is incomplete
     */
    public function isIncomplete(): bool
    {
        return !in_array($this->status, ['COMPLETED', 'PARTIAL', 'FAILED']);
    }

    /**
     * Check if batch has failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'FAILED';
    }

    /**
     * Check if batch is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    /**
     * Check if batch is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === 'IN_PROGRESS';
    }

    /**
     * Check if batch is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }

    /**
     * Check if batch is partial (completed with some failures)
     */
    public function isPartial(): bool
    {
        return $this->status === 'PARTIAL';
    }

    /**
     * Get the count of items in this batch
     */
    public function getItemCountAttribute(): int
    {
        return count($this->item_ids ?? []);
    }

    /**
     * Handle batch failure
     */
    public function onFail(\Throwable $e): void
    {
        Log::error("Enrichment batch {$this->id} failed: " . $e->getMessage());

        $this->update([
            'status' => 'FAILED',
            'error_message' => $e->getMessage(),
            'completed_at' => now()
        ]);
    }
}
