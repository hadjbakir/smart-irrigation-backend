<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = [
        'zone_id',
        'sensor_id',
        'level',
        'type',
        'message',
        'handled',
    ];

    protected $casts = [
        'handled' => 'boolean',
    ];

    /**
     * Get the zone associated with this alert
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Get the sensor associated with this alert
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    /**
     * Scope to get unhandled alerts
     */
    public function scopeUnhandled($query)
    {
        return $query->where('handled', false);
    }

    /**
     * Scope to get alerts by level
     */
    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }
}












