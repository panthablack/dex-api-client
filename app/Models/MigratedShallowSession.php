<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MigratedShallowSession extends Model
{
    use HasFactory;
    protected $fillable = [
        'session_id',
        'case_id',
    ];

    public function enrichedSession(): HasOne
    {
        return $this->hasOne(MigratedEnrichedSession::class, 'session_id', 'session_id')
            ->where('case_id', $this->case_id);
    }

    /**
     * Check if this session has been enriched
     */
    public function isEnriched(): bool
    {
        return $this->enrichedSession()->exists();
    }
}
