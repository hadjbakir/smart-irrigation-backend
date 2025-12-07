<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Weather extends Model
{
    protected $fillable = [
        'latitude',
        'longitude',
        'temperature',
        'humidity',
        'pressure',
        'wind_speed',
        'condition',
        'description',
        'is_raining',
        'rain_amount',
        'data',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'double',
        'longitude' => 'double',
        'temperature' => 'double',
        'humidity' => 'integer',
        'pressure' => 'double',
        'wind_speed' => 'double',
        'is_raining' => 'boolean',
        'rain_amount' => 'double',
        'data' => 'array',
        'recorded_at' => 'datetime',
    ];
}
