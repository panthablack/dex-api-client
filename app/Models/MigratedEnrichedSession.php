<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MigratedEnrichedSession extends Model
{
    protected $fillable = [
        'case_id',
        'session_id',
        'shallow_session_id',
        'session_date',
        'service_type_id',
        'total_number_of_unidentified_clients',
        'fees_charged',
        'money_business_community_education_workshop_code',
        'interpreter_present',
        'service_setting_code',
        'api_response',
        'data_migration_batch_id',
        'enriched_at',
        'verification_status',
        'verified_at',
        'verification_error'
    ];

    protected $casts = [
        'session_date' => 'date',
        'interpreter_present' => 'boolean',
        'api_response' => 'array',
        'verification_status' => VerificationStatus::class,
        'verified_at' => 'datetime'
    ];

    public function shallowSession(): BelongsTo
    {
        return $this->belongsTo(MigratedShallowSession::class, 'session_id', 'session_id')
            ->where('case_id', $this->case_id);
    }

    public function migratedCase(): BelongsTo
    {
        return $this->belongsTo(MigratedCase::class, 'case_id', 'case_id');
    }

    public function enrichedCase(): BelongsTo
    {
        return $this->belongsTo(MigratedEnrichedCase::class, 'case_id', 'case_id');
    }
}
