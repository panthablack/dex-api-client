<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MigratedCase extends Model
{
    protected $fillable = [
        'migration_id',
        'case_id',
        'client_id',
        'outlet_activity_id',
        'referral_source_code',
        'reasons_for_assistance',
        'total_unidentified_clients',
        'client_attendance_profile_code',
        'end_date',
        'exit_reason_code',
        'ag_business_type_code',
        'api_response',
        'migration_batch_id',
        'migrated_at',
        'verification_status',
        'verified_at',
        'verification_error'
    ];

    protected $casts = [
        'reasons_for_assistance' => 'array',
        'end_date' => 'date',
        'api_response' => 'array',
        'migrated_at' => 'datetime',
        'verification_status' => VerificationStatus::class,
        'verified_at' => 'datetime'
    ];

    public function migration(): BelongsTo
    {
        return $this->belongsTo(DataMigration::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(MigratedClient::class, 'client_id', 'client_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(MigratedSession::class, 'case_id', 'case_id');
    }
}
