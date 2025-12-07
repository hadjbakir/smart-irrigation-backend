<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Action extends Model
{
    protected $fillable = [
        'sensor_id',
        'action',
        'payload',
        'status',
        'requested_at',
        'executed_at',
        'retry_count',
        'max_retries',
        'execution_time_ms',
    ];

    protected $casts = [
        'payload' => 'array',
        'requested_at' => 'datetime',
        'executed_at' => 'datetime',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
        'execution_time_ms' => 'integer',
    ];

    /**
     * Get the sensor associated with this action
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    /**
     * Scope to get pending actions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get completed actions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'done');
    }

    /**
     * Scope to get failed actions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}







