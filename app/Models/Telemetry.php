<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Telemetry extends Model
{
    protected $table = 'telemetry';

    protected $fillable = [
        'sensor_id',
        'metric',
        'value',
        'recorded_at',
    ];

    protected $casts = [
        'value' => 'double',
        'recorded_at' => 'datetime',
    ];

    /**
     * Get the sensor that owns this telemetry
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }
}

