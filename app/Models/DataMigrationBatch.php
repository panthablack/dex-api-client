<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataMigrationBatch extends Model
{
    use HasFactory;
    protected $fillable = [
        'batch_id',
        'data_migration_id',
        'resource_type',
        'batch_number',
        'page_index',
        'page_size',
        'status',
        'items_requested',
        'items_received',
        'items_stored',
        'error_message',
        'api_filters',
        'api_response_summary',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'api_filters' => 'array',
        'api_response_summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    public function dataMigration(): BelongsTo
    {
        return $this->belongsTo(DataMigration::class);
    }

    public function scopeForResource($query, string $resourceType)
    {
        return $query->where('resource_type', $resourceType);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function getSuccessRateAttribute()
    {
        if ($this->items_received == 0) {
            return 0;
        }

        return round(($this->items_stored / $this->items_received) * 100, 2);
    }
}
