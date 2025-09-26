<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigratedClient extends Model
{
    protected $fillable = [
        'client_id',
        'slk',
        'consent_to_provide_details',
        'consented_for_future_contacts',
        'given_name',
        'family_name',
        'is_using_psuedonym',
        'birth_date',
        'is_birth_date_an_estimate',
        'gender_code',
        'gender_details',
        'residential_address',
        'country_of_birth_code',
        'language_spoken_at_home_code',
        'aboriginal_or_torres_strait_islander_origin_code',
        'has_disabilities',
        'api_response',
        'data_migration_batch_id',
        'verification_status',
        'verified_at',
        'verification_error'
    ];

    protected $casts = [
        'consent_to_provide_details' => 'boolean',
        'consented_for_future_contacts' => 'boolean',
        'is_using_psuedonym' => 'boolean',
        'birth_date' => 'date',
        'is_birth_date_an_estimate' => 'boolean',
        'has_disabilities' => 'boolean',
        'residential_address' => 'array',
        'api_response' => 'array',
        'verification_status' => VerificationStatus::class,
        'verified_at' => 'datetime'
    ];

    public function migration(): BelongsTo
    {
        return $this->belongsTo(DataMigration::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(MigratedCase::class, 'client_id', 'client_id');
    }
}
