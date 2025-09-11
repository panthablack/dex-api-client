<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataMigration extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'resource_types',
        'filters',
        'status',
        'total_items',
        'processed_items',
        'successful_items',
        'failed_items',
        'batch_size',
        'error_message',
        'summary',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'resource_types' => 'array',
        'filters' => 'array',
        'summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    public function batches(): HasMany
    {
        return $this->hasMany(DataMigrationBatch::class);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'in_progress']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->total_items == 0) {
            return 0;
        }
        
        return round(($this->processed_items / $this->total_items) * 100, 2);
    }

    public function getSuccessRateAttribute()
    {
        if ($this->processed_items == 0) {
            return 0;
        }
        
        return round(($this->successful_items / $this->processed_items) * 100, 2);
    }
}
