<?php

namespace App\Models;

use App\Enums\DataMigrationStatus;
use App\Enums\ResourceType;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DataMigration extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'resource_type',
        'filters',
        'status',
        'total_items',
        'batch_size',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'resource_type' => ResourceType::class,
        'filters' => 'array',
        'status' => DataMigrationStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    public function batches(): HasMany
    {
        return $this->hasMany(DataMigrationBatch::class);
    }

    public function incompleteBatches(): Collection
    {
        return $this->batches->filter(
            fn(DataMigrationBatch $batch) => $batch->isIncomplete()
        );
    }

    public function pendingBatches(): Collection
    {
        return $this->batches->filter(
            fn(DataMigrationBatch $batch) => $batch->isPending()
        );
    }

    // Direct relationships to migrated records
    public function clients(): HasManyThrough
    {
        return $this->hasManyThrough(MigratedClient::class, DataMigrationBatch::class);
    }

    public function cases(): HasManyThrough
    {
        return $this->hasManyThrough(MigratedCase::class, DataMigrationBatch::class);
    }

    public function sessions(): HasManyThrough
    {
        return $this->hasManyThrough(MigratedSession::class, DataMigrationBatch::class);
    }

    /**
     * Handle migration failure.
     */
    public function onFail(\Throwable $e): void
    {
        Log::error($e);
        $this->update([
            'status' => DataMigrationStatus::FAILED,
            'completed_at' => now()
        ]);
    }

    /**
     * Scope a query to only include active migrations.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            DataMigrationStatus::PENDING,
            DataMigrationStatus::IN_PROGRESS
        ]);
    }

    /**
     * Scope a query to only include completed migrations.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', DataMigrationStatus::COMPLETED);
    }

    /**
     * Scope a query to only include failed migrations.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', DataMigrationStatus::FAILED);
    }

    /**
     * Get the total number of processed items across all batches.
     */
    public function getProcessedItemsAttribute(): int
    {
        return $this->batches->sum('items_stored') ?? 0;
    }

    /**
     * Get the progress percentage based on processed vs total items.
     */
    public function getProgressPercentageAttribute(): float
    {
        if (!$this->total_items || $this->total_items == 0) {
            return 0.0;
        }

        $processed = $this->processed_items;
        $percentage = ($processed / $this->total_items) * 100;

        return round($percentage, 1);
    }

    /**
     * Get the total number of successful items across all batches.
     */
    public function getSuccessfulItemsAttribute(): int
    {
        return $this->batches->sum('items_stored') ?? 0;
    }

    /**
     * Get the total number of failed items across all batches.
     */
    public function getFailedItemsAttribute(): int
    {
        $totalReceived = $this->batches->sum('items_received') ?? 0;
        $totalStored = $this->batches->sum('items_stored') ?? 0;
        return $totalReceived - $totalStored;
    }

    /**
     * Get the success rate percentage based on stored vs received items.
     */
    public function getSuccessRateAttribute(): float
    {
        $totalReceived = $this->batches->sum('items_received') ?? 0;

        if ($totalReceived == 0) {
            return 0.0;
        }

        $totalStored = $this->batches->sum('items_stored') ?? 0;
        $percentage = ($totalStored / $totalReceived) * 100;

        return round($percentage, 1);
    }

    public function endResources(ResourceType $type): HasManyThrough
    {
        if ($type === ResourceType::CLIENT) return $this->hasManyThrough(
            MigratedClient::class,
            DataMigrationBatch::class
        );
        if ($type === ResourceType::CASE) return $this->hasManyThrough(
            MigratedCase::class,
            DataMigrationBatch::class
        );
        if ($type === ResourceType::SESSION) return $this->hasManyThrough(
            MigratedSession::class,
            DataMigrationBatch::class
        );
        else throw new \Exception('Resource type not supported for end resources');
    }
    public function getResourceVerificationInfo(ResourceType $type): array
    {
        return [
            'total' => $this->endResources($type)->count(),
            VerificationStatus::PENDING->value => $this->endResources($type)->where(
                'verification_status',
                '=',
                VerificationStatus::PENDING->value
            )->pluck($type->getTableName() . '.id'),
            VerificationStatus::VERIFIED->value => $this->endResources($type)->where(
                'verification_status',
                '=',
                VerificationStatus::VERIFIED->value
            )->pluck($type->getTableName() . '.id'),
            VerificationStatus::FAILED->value => $this->endResources($type)->where(
                'verification_status',
                '=',
                VerificationStatus::FAILED->value
            )->pluck($type->getTableName() . '.id'),
        ];
    }

    public function getVerificationInfo()
    {
        return [
            ResourceType::CLIENT->value => $this->getResourceVerificationInfo(
                ResourceType::CLIENT
            ),
            ResourceType::CASE->value => $this->getResourceVerificationInfo(
                ResourceType::CASE
            ),
            ResourceType::SESSION->value => $this->getResourceVerificationInfo(
                ResourceType::SESSION
            ),
        ];
    }
}
