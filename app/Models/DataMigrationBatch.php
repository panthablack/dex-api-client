<?php

namespace App\Models;

use App\Enums\DataMigrationBatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataMigrationBatch extends Model
{
    use HasFactory;
    protected $fillable = [
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
        'status' => DataMigrationBatchStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    public function dataMigration(): BelongsTo
    {
        return $this->belongsTo(DataMigration::class);
    }

    /**
     * Handle batch failure.
     */
    public function onFail(\Throwable $e): void
    {
        $this->update([
            'status' => DataMigrationBatchStatus::FAILED,
            'error_message' => $e->getMessage(),
            'completed_at' => now()
        ]);
    }
}
