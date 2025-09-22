<?php

namespace App\Models;

use App\Enums\DataMigrationBatchStatus;
use App\Enums\ResourceType;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function allItemsVerified(): bool
    {
        return $this->items->where('verification_status', '=', VerificationStatus::VERIFIED->value)->count() === $this->items->count();
    }

    public function anItemHasFailedVerification(): bool
    {
        return $this->items->where('verification_status', '=', VerificationStatus::FAILED->value)->count() > 0;
    }

    public function items(): HasMany
    {
        $type = ResourceType::resolve($this->resource_type);
        if ($type === ResourceType::CLIENT) return $this->hasMany(MigratedClient::class);
        if ($type === ResourceType::CASE) return $this->hasMany(MigratedCase::class);
        if ($type === ResourceType::SESSION) return $this->hasMany(MigratedSession::class);
        else throw new \Exception('Resource type not supported for batches');
    }

    public function getVerificationStatus(): VerificationStatus
    {
        // if all resources in the batch are verified, return verified
        if ($this->allItemsVerified()) return VerificationStatus::VERIFIED;
        // if any resource in the batch has failed, return failed
        if ($this->anItemHasFailedVerification()) return VerificationStatus::FAILED;
        // else, return pending
        else return VerificationStatus::PENDING;
    }
}
