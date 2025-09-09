<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MigratedClient extends Model
{
    protected $fillable = [
        'client_id',
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
        'migration_batch_id',
        'migrated_at'
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
        'migrated_at' => 'datetime'
    ];

    public function cases(): HasMany
    {
        return $this->hasMany(MigratedCase::class, 'client_id', 'client_id');
    }
}
