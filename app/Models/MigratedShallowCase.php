<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MigratedShallowCase extends Model
{
    protected $fillable = [
        'case_id',
        'outlet_name',
        'created_date_time',
        'client_attendance_profile_code',
        'api_response',
        'data_migration_batch_id',
    ];

    protected $casts = [
        'created_date_time' => 'date',
        'api_response' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DataMigrationBatch::class, 'data_migration_batch_id');
    }

    public function enrichedCase(): HasOne
    {
        return $this->hasOne(MigratedEnrichedCase::class, 'shallow_case_id');
    }

    /**
     * Check if this case has been enriched
     */
    public function isEnriched(): bool
    {
        return $this->enrichedCase()->exists();
    }
}
