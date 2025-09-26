<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigratedCaseClient extends Model
{
    protected $fillable = [
        'client_id',
        'data_migration_batch_id',
        'first_name',
        'last_name',
        'date_of_birth',
        'is_birth_date_estimate',
        'gender',
        'suburb',
        'state',
        'postal_code',
        'country_of_birth',
        'primary_language',
        'indigenous_status',
        'interpreter_required',
        'disability_flag',
        'is_using_pseudonym',
        'consent_to_provide_details',
        'consent_to_be_contacted',
        'client_type',
        'api_response',
        'verification_status',
        'verified_at',
        'verification_error'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_birth_date_estimate' => 'boolean',
        'interpreter_required' => 'boolean',
        'disability_flag' => 'boolean',
        'is_using_pseudonym' => 'boolean',
        'consent_to_provide_details' => 'boolean',
        'consent_to_be_contacted' => 'boolean',
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
        return $this->belongsTo(MigratedCase::class);
    }
}
