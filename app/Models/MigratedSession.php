<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigratedSession extends Model
{
    protected $fillable = [
        'session_id',
        'case_id',
        'data_migration_batch_id',
        'service_type_id',
        'session_date',
        'duration_minutes',
        'location',
        'session_status',
        'attendees',
        'outcome',
        'notes',
        'api_response',
        'verification_status',
        'verified_at',
        'verification_error'
    ];

    protected $casts = [
        'session_date' => 'date',
        'api_response' => 'array',
        'verification_status' => VerificationStatus::class,
        'verified_at' => 'datetime'
    ];

    public function migration(): BelongsTo
    {
        return $this->belongsTo(DataMigration::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(MigratedCase::class, 'case_id', 'case_id');
    }
}
