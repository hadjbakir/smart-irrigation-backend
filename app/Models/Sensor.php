<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sensor extends Model
{
    protected $fillable = [
        'zone_id',
        'type',
        'name',
        'meta',
        'battery_level',
        'last_battery_update',
        'pump_started_at',
        'valve_opened_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'battery_level' => 'double',
        'last_battery_update' => 'datetime',
        'pump_started_at' => 'datetime',
        'valve_opened_at' => 'datetime',
    ];

    /**
     * Get the zone that owns this sensor
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Get all telemetry records for this sensor
     */
    public function telemetry(): HasMany
    {
        return $this->hasMany(Telemetry::class);
    }

    /**
     * Get all actions for this sensor
     */
    public function actions(): HasMany
    {
        return $this->hasMany(Action::class);
    }

    /**
     * Get all alerts for this sensor
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Get latest telemetry for a specific metric
     */
    public function getLatestTelemetry(string $metric): ?Telemetry
    {
        return $this->telemetry()
            ->where('metric', $metric)
            ->latest('recorded_at')
            ->first();
    }
}







