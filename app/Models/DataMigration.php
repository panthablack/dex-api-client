<?php

namespace App\Models;

use App\Enums\DataMigrationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class DataMigration extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'resource_types',
        'filters',
        'status',
        'total_items',
        'batch_size',
        'error_message',
        'summary',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'resource_types' => 'array',
        'filters' => 'array',
        'summary' => 'array',
        'status' => DataMigrationStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    public function batches(): HasMany
    {
        return $this->hasMany(DataMigrationBatch::class);
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
        $this->update([
            'status' => DataMigrationStatus::FAILED,
            'error_message' => $e->getMessage(),
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
}
