<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneRule extends Model
{
    protected $fillable = [
        'zone_id',
        'moisture_threshold',
        'moisture_target',
        'pump_overload_current',
        'flow_leak_multiplier',
        'enable_low_moisture',
        'enable_pump_overload',
        'enable_leak_detection',
        'enable_rain_forecast',
        'irrigation_duration_minutes',
        'schedule',
        'max_pump_duration_minutes',
        'max_valve_duration_minutes',
        'pressure_leak_threshold_multiplier',
        'pressure_blockage_threshold_multiplier',
        'enable_pressure_monitoring',
        'battery_low_threshold',
        'enable_battery_monitoring',
        'stuck_sensor_timeout_minutes',
        'enable_stuck_sensor_detection',
    ];

    protected $casts = [
        'moisture_threshold' => 'double',
        'moisture_target' => 'double',
        'pump_overload_current' => 'double',
        'flow_leak_multiplier' => 'double',
        'enable_low_moisture' => 'boolean',
        'enable_pump_overload' => 'boolean',
        'enable_leak_detection' => 'boolean',
        'enable_rain_forecast' => 'boolean',
        'irrigation_duration_minutes' => 'integer',
        'schedule' => 'array',
        'max_pump_duration_minutes' => 'integer',
        'max_valve_duration_minutes' => 'integer',
        'pressure_leak_threshold_multiplier' => 'double',
        'pressure_blockage_threshold_multiplier' => 'double',
        'enable_pressure_monitoring' => 'boolean',
        'battery_low_threshold' => 'double',
        'enable_battery_monitoring' => 'boolean',
        'stuck_sensor_timeout_minutes' => 'integer',
        'enable_stuck_sensor_detection' => 'boolean',
    ];

    /**
     * Get the zone that owns this rule
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Get default rules (used when zone doesn't have custom rules)
     */
    public static function getDefaults(): array
    {
        return [
            'moisture_threshold' => 30.0,
            'moisture_target' => 60.0,
            'pump_overload_current' => 15.0,
            'flow_leak_multiplier' => 1.5,
            'enable_low_moisture' => true,
            'enable_pump_overload' => true,
            'enable_leak_detection' => true,
            'enable_rain_forecast' => true,
            'irrigation_duration_minutes' => null,
            'schedule' => null,
            'max_pump_duration_minutes' => 60,
            'max_valve_duration_minutes' => 120,
            'pressure_leak_threshold_multiplier' => 1.3,
            'pressure_blockage_threshold_multiplier' => 0.7,
            'enable_pressure_monitoring' => true,
            'battery_low_threshold' => 20.0,
            'enable_battery_monitoring' => true,
            'stuck_sensor_timeout_minutes' => 30,
            'enable_stuck_sensor_detection' => true,
        ];
    }
}


