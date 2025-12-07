<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    protected $fillable = [
        'name',
        'notes',
        'mode',
        'manual_notes',
    ];

    protected $casts = [
        'mode' => 'string',
    ];

    /**
     * Get all sensors for this zone
     */
    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class);
    }

    /**
     * Get all alerts for this zone
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Get the rules for this zone
     */
    public function rules(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ZoneRule::class);
    }

    /**
     * Get rules with defaults if not set
     */
    public function getRulesWithDefaults(): ZoneRule
    {
        return $this->rules ?? ZoneRule::make(ZoneRule::getDefaults());
    }

    /**
     * Check if zone is in manual mode
     */
    public function isManualMode(): bool
    {
        return $this->mode === 'manual';
    }

    /**
     * Check if zone is in auto mode
     */
    public function isAutoMode(): bool
    {
        return $this->mode === 'auto';
    }
}






