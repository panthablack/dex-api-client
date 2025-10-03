<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MigratedEnrichedCase extends Model
{
    protected $fillable = [
        'case_id',
        'shallow_case_id',
        'outlet_name',
        'client_ids',
        'outlet_activity_id',
        'total_number_of_unidentified_clients',
        'client_attendance_profile_code',
        'created_date_time',
        'end_date',
        'exit_reason_code',
        'ag_business_type_code',
        'program_activity_name',
        'api_response',
        'enriched_at',
        'verification_status',
        'verified_at',
        'verification_error',
    ];

    protected $casts = [
        'client_ids' => 'array',
        'created_date_time' => 'date',
        'end_date' => 'date',
        'api_response' => 'array',
        'enriched_at' => 'datetime',
        'verification_status' => VerificationStatus::class,
        'verified_at' => 'datetime',
    ];

    public function shallowCase(): BelongsTo
    {
        return $this->belongsTo(MigratedShallowCase::class, 'shallow_case_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(MigratedSession::class, 'case_id', 'case_id');
    }

    public function clients()
    {
        if (!$this->client_ids) {
            return collect();
        }
        return MigratedClient::whereIn('client_id', $this->client_ids)->get();
    }
}
