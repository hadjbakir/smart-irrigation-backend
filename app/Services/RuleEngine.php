<?php

namespace App\Services;

use App\Models\Sensor;
use App\Models\Action;
use App\Models\Alert;
use App\Models\Zone;
use App\Models\ZoneRule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RuleEngine
{
    /**
     * Default thresholds (fallback if zone has no rules)
     */
    private const MOISTURE_THRESHOLD = 30.0;
    private const MOISTURE_TARGET = 60.0;
    private const PUMP_OVERLOAD_CURRENT = 15.0;
    private const FLOW_LEAK_THRESHOLD_MULTIPLIER = 1.5; // 50% above expected

    /**
     * Evaluate all automation rules
     */
    public function evaluate(): void
    {
        Log::info('Rule Engine: Starting evaluation cycle');

        $zones = Zone::with(['sensors', 'rules'])->get();

        foreach ($zones as $zone) {
            // Skip manual mode zones
            if ($zone->isManualMode()) {
                Log::debug("Rule Engine: Skipping zone {$zone->id} (manual mode)");
                continue;
            }

            $this->evaluateZone($zone);
        }

        Log::info('Rule Engine: Evaluation cycle completed');
    }

    /**
     * Evaluate rules for a specific zone
     */
    private function evaluateZone(Zone $zone): void
    {
        $sensors = $zone->sensors;
        $rules = $this->getZoneRules($zone);

        foreach ($sensors as $sensor) {
            $this->evaluateSensor($sensor, $rules);
        }
    }

    /**
     * Get zone rules with defaults
     */
    private function getZoneRules(Zone $zone): ZoneRule
    {
        return $zone->rules ?? ZoneRule::make(ZoneRule::getDefaults());
    }

    /**
     * Evaluate rules for a specific sensor
     */
    private function evaluateSensor(Sensor $sensor, ZoneRule $rules): void
    {
        // Rule 1: Low soil moisture → start pump
        if ($rules->enable_low_moisture) {
            $this->checkLowMoisture($sensor, $rules);
        }

        // Rule 2: Pump/Valve runtime duration check → auto-shutoff
        $this->checkRuntimeDuration($sensor, $rules);

        // Rule 3: Pump overload → emergency shutdown
        if ($rules->enable_pump_overload) {
            $this->checkPumpOverload($sensor, $rules);
        }

        // Rule 4: Leak detection → auto shutdown
        if ($rules->enable_leak_detection) {
            $this->checkLeakDetection($sensor, $rules);
        }

        // Rule 5: Pressure monitoring → leak/blockage detection
        if ($rules->enable_pressure_monitoring ?? true) {
            $this->checkPressureMonitoring($sensor, $rules);
        }

        // Rule 6: Battery monitoring
        if ($rules->enable_battery_monitoring ?? true) {
            $this->checkBatteryLevel($sensor, $rules);
        }

        // Rule 7: Stuck sensor detection
        if ($rules->enable_stuck_sensor_detection ?? true) {
            $this->checkStuckSensor($sensor, $rules);
        }
    }

    /**
     * Rule 1: Low soil moisture → start pump
     */
    private function checkLowMoisture(Sensor $sensor, ZoneRule $rules): void
    {
        if ($sensor->type !== 'soil_sensor') {
            return;
        }

        $latestMoisture = $sensor->getLatestTelemetry('moisture');

        if (!$latestMoisture) {
            return;
        }

        $threshold = $rules->moisture_threshold ?? self::MOISTURE_THRESHOLD;
        $target = $rules->moisture_target ?? self::MOISTURE_TARGET;

        // Check if moisture is below threshold
        if ($latestMoisture->value < $threshold) {
            // Find pump sensor in the same zone
            $pumpSensor = Sensor::where('zone_id', $sensor->zone_id)
                ->where('type', 'pump')
                ->first();

            if (!$pumpSensor) {
                return;
            }

            // Check if pump is already ON
            $pumpStatus = $pumpSensor->getLatestTelemetry('pump_status');
            if ($pumpStatus && $pumpStatus->value == 1) {
                return; // Already ON
            }

            // Check if there's already a pending pump_on action
            $existingAction = Action::where('sensor_id', $pumpSensor->id)
                ->where('action', 'pump_on')
                ->where('status', 'pending')
                ->first();

            if ($existingAction) {
                return; // Action already queued
            }

            // Create pump_on action
            Action::create([
                'sensor_id' => $pumpSensor->id,
                'action' => 'pump_on',
                'payload' => [
                    'triggered_by' => 'low_moisture',
                    'moisture_level' => $latestMoisture->value,
                    'threshold' => $threshold,
                    'target' => $target,
                ],
                'status' => 'pending',
                'requested_at' => now(),
                'max_retries' => 3,
            ]);

            // Create warning alert
            Alert::create([
                'zone_id' => $sensor->zone_id,
                'sensor_id' => $sensor->id,
                'level' => 'warning',
                'type' => 'low_moisture',
                'message' => "Low soil moisture detected ({$latestMoisture->value}% < {$threshold}%). Starting irrigation pump.",
                'handled' => false,
            ]);

            Log::info("Rule Engine: Low moisture detected for sensor {$sensor->id} (threshold: {$threshold}%), created pump_on action");
        }

        // Also check if moisture has reached target and pump should stop
        if ($latestMoisture->value >= $target) {
            $pumpSensor = Sensor::where('zone_id', $sensor->zone_id)
                ->where('type', 'pump')
                ->first();

            if ($pumpSensor) {
                $pumpStatus = $pumpSensor->getLatestTelemetry('pump_status');
                if ($pumpStatus && $pumpStatus->value == 1) {
                    // Check if there's already a pending pump_off action
                    $existingAction = Action::where('sensor_id', $pumpSensor->id)
                        ->where('action', 'pump_off')
                        ->where('status', 'pending')
                        ->first();

                    if (!$existingAction) {
                        Action::create([
                            'sensor_id' => $pumpSensor->id,
                            'action' => 'pump_off',
                            'payload' => [
                                'triggered_by' => 'moisture_target_reached',
                                'moisture_level' => $latestMoisture->value,
                                'target' => $target,
                            ],
                            'status' => 'pending',
                            'requested_at' => now(),
                            'max_retries' => 3,
                        ]);

                        Log::info("Rule Engine: Moisture target reached for sensor {$sensor->id} ({$latestMoisture->value}% >= {$target}%), created pump_off action");
                    }
                }
            }
        }
    }

    /**
     * Rule 3: Pump overload → emergency shutdown
     */
    private function checkPumpOverload(Sensor $sensor, ZoneRule $rules): void
    {
        if ($sensor->type !== 'pump') {
            return;
        }

        $latestCurrent = $sensor->getLatestTelemetry('current');

        if (!$latestCurrent) {
            return;
        }

        $threshold = $rules->pump_overload_current ?? self::PUMP_OVERLOAD_CURRENT;

        if ($latestCurrent->value > $threshold) {
            // Check if pump is already OFF
            $pumpStatus = $sensor->getLatestTelemetry('pump_status');
            if ($pumpStatus && $pumpStatus->value == 0) {
                return; // Already OFF
            }

            // Create immediate pump_off action
            Action::create([
                'sensor_id' => $sensor->id,
                'action' => 'pump_off',
                'payload' => [
                    'triggered_by' => 'pump_overload',
                    'current' => $latestCurrent->value,
                    'threshold' => $threshold,
                    'emergency' => true,
                ],
                'status' => 'pending',
                'requested_at' => now(),
                'max_retries' => 3,
            ]);

            // Create critical alert
            Alert::create([
                'zone_id' => $sensor->zone_id,
                'sensor_id' => $sensor->id,
                'level' => 'critical',
                'type' => 'pump_overload',
                'message' => "Pump overload detected ({$latestCurrent->value}A > {$threshold}A). Emergency shutdown initiated.",
                'handled' => false,
            ]);

            Log::warning("Rule Engine: Pump overload detected for sensor {$sensor->id} (threshold: {$threshold}A), emergency shutdown initiated");
        }
    }

    /**
     * Rule 4: Leak detection → auto shutdown
     */
    private function checkLeakDetection(Sensor $sensor, ZoneRule $rules): void
    {
        if ($sensor->type !== 'flow') {
            return;
        }

        $latestFlow = $sensor->getLatestTelemetry('flow');
        $pumpSensor = Sensor::where('zone_id', $sensor->zone_id)
            ->where('type', 'pump')
            ->first();

        if (!$latestFlow || !$pumpSensor) {
            return;
        }

        // Check if pump is ON
        $pumpStatus = $pumpSensor->getLatestTelemetry('pump_status');
        if (!$pumpStatus || $pumpStatus->value == 0) {
            return; // Pump is OFF, no leak check needed
        }

        // Expected flow when pump is ON (can be configured per zone/sensor)
        $expectedFlow = $sensor->meta['expected_flow'] ?? 10.0; // Default 10 L/min
        $multiplier = $rules->flow_leak_multiplier ?? self::FLOW_LEAK_THRESHOLD_MULTIPLIER;
        $leakThreshold = $expectedFlow * $multiplier;

        if ($latestFlow->value > $leakThreshold) {
            // Create emergency shutdown actions
            Action::create([
                'sensor_id' => $pumpSensor->id,
                'action' => 'pump_off',
                'payload' => [
                    'triggered_by' => 'leak_detection',
                    'flow' => $latestFlow->value,
                    'expected_flow' => $expectedFlow,
                    'emergency' => true,
                ],
                'status' => 'pending',
                'requested_at' => now(),
                'max_retries' => 3,
            ]);

            // Find valve sensor and close it
            $valveSensor = Sensor::where('zone_id', $sensor->zone_id)
                ->where('type', 'valve')
                ->first();

            if ($valveSensor) {
                Action::create([
                    'sensor_id' => $valveSensor->id,
                    'action' => 'close_valve',
                    'payload' => [
                        'triggered_by' => 'leak_detection',
                        'emergency' => true,
                    ],
                    'status' => 'pending',
                    'requested_at' => now(),
                    'max_retries' => 3,
                ]);
            }

            // Create critical alert
            Alert::create([
                'zone_id' => $sensor->zone_id,
                'sensor_id' => $sensor->id,
                'level' => 'critical',
                'type' => 'leak',
                'message' => "Leak detected! Flow rate ({$latestFlow->value} L/min) exceeds threshold ({$leakThreshold} L/min). Emergency shutdown initiated.",
                'handled' => false,
            ]);

            Log::error("Rule Engine: Leak detected for sensor {$sensor->id} (threshold: {$leakThreshold} L/min), emergency shutdown initiated");
        }
    }

    /**
     * Rule 2: Rain forecast → postpone irrigation
     * This is triggered manually via API endpoint
     */
    public function handleRainForecast(int $zoneId): void
    {
        $zone = Zone::with('rules')->find($zoneId);

        if (!$zone) {
            return;
        }

        $rules = $this->getZoneRules($zone);

        // Check if rain forecast rule is enabled
        if (!$rules->enable_rain_forecast) {
            Log::info("Rule Engine: Rain forecast rule disabled for zone {$zoneId}");
            return;
        }

        // Cancel all pending irrigation actions for this zone
        $pumpSensors = Sensor::where('zone_id', $zoneId)
            ->where('type', 'pump')
            ->pluck('id');

        Action::whereIn('sensor_id', $pumpSensors)
            ->where('action', 'pump_on')
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        // Create info alert
        Alert::create([
            'zone_id' => $zoneId,
            'level' => 'info',
            'type' => 'rain_forecast',
            'message' => 'Rain forecast received. Irrigation postponed.',
            'handled' => false,
        ]);

        Log::info("Rule Engine: Rain forecast received for zone {$zoneId}, irrigation postponed");
    }

    /**
     * Rule: Check pump/valve runtime duration → auto-shutoff to prevent overload
     */
    private function checkRuntimeDuration(Sensor $sensor, ZoneRule $rules): void
    {
        $maxPumpDuration = $rules->max_pump_duration_minutes ?? 60;
        $maxValveDuration = $rules->max_valve_duration_minutes ?? 120;

        // Check pump runtime
        if ($sensor->type === 'pump') {
            $pumpStatus = $sensor->getLatestTelemetry('pump_status');
            if ($pumpStatus && $pumpStatus->value == 1) {
                // Pump is ON, check runtime
                $startTime = $sensor->pump_started_at ?? $pumpStatus->recorded_at;
                $runtimeMinutes = now()->diffInMinutes($startTime);

                if ($runtimeMinutes >= $maxPumpDuration) {
                    // Check if there's already a pending pump_off action
                    $existingAction = Action::where('sensor_id', $sensor->id)
                        ->where('action', 'pump_off')
                        ->where('status', 'pending')
                        ->first();

                    if (!$existingAction) {
                        // Create pump_off action
                        Action::create([
                            'sensor_id' => $sensor->id,
                            'action' => 'pump_off',
                            'payload' => [
                                'triggered_by' => 'max_runtime',
                                'runtime_minutes' => $runtimeMinutes,
                                'max_duration' => $maxPumpDuration,
                            ],
                            'status' => 'pending',
                            'requested_at' => now(),
                            'max_retries' => 3,
                        ]);

                        Alert::create([
                            'zone_id' => $sensor->zone_id,
                            'sensor_id' => $sensor->id,
                            'level' => 'warning',
                            'type' => 'pump_max_runtime',
                            'message' => "Pump has been running for {$runtimeMinutes} minutes (max: {$maxPumpDuration} min). Auto-shutting down to prevent overload.",
                            'handled' => false,
                        ]);

                        Log::info("Rule Engine: Pump {$sensor->id} exceeded max runtime ({$runtimeMinutes} min), created pump_off action");
                    }
                }
            }
        }

        // Check valve runtime
        if ($sensor->type === 'valve') {
            $valveStatus = $sensor->getLatestTelemetry('valve_status');
            if ($valveStatus && $valveStatus->value == 1) {
                // Valve is OPEN, check runtime
                $startTime = $sensor->valve_opened_at ?? $valveStatus->recorded_at;
                $runtimeMinutes = now()->diffInMinutes($startTime);

                if ($runtimeMinutes >= $maxValveDuration) {
                    // Check if there's already a pending close_valve action
                    $existingAction = Action::where('sensor_id', $sensor->id)
                        ->where('action', 'close_valve')
                        ->where('status', 'pending')
                        ->first();

                    if (!$existingAction) {
                        // Create close_valve action
                        Action::create([
                            'sensor_id' => $sensor->id,
                            'action' => 'close_valve',
                            'payload' => [
                                'triggered_by' => 'max_runtime',
                                'runtime_minutes' => $runtimeMinutes,
                                'max_duration' => $maxValveDuration,
                            ],
                            'status' => 'pending',
                            'requested_at' => now(),
                            'max_retries' => 3,
                        ]);

                        Alert::create([
                            'zone_id' => $sensor->zone_id,
                            'sensor_id' => $sensor->id,
                            'level' => 'warning',
                            'type' => 'valve_max_runtime',
                            'message' => "Valve has been open for {$runtimeMinutes} minutes (max: {$maxValveDuration} min). Auto-closing.",
                            'handled' => false,
                        ]);

                        Log::info("Rule Engine: Valve {$sensor->id} exceeded max runtime ({$runtimeMinutes} min), created close_valve action");
                    }
                }
            }
        }
    }

    /**
     * Rule: Pressure monitoring → detect leaks (pressure increase) or blockages (pressure decrease)
     */
    private function checkPressureMonitoring(Sensor $sensor, ZoneRule $rules): void
    {
        if ($sensor->type !== 'pressure_sensor') {
            return;
        }

        $latestPressure = $sensor->getLatestTelemetry('pressure');
        if (!$latestPressure) {
            return;
        }

        // Get average pressure from last hour to establish baseline
        $oneHourAgo = now()->subHour();
        $avgPressure = \DB::table('telemetry')
            ->where('sensor_id', $sensor->id)
            ->where('metric', 'pressure')
            ->where('recorded_at', '>=', $oneHourAgo)
            ->avg('value');

        if (!$avgPressure) {
            return; // Not enough data
        }

        $leakThreshold = $avgPressure * ($rules->pressure_leak_threshold_multiplier ?? 1.3);
        $blockageThreshold = $avgPressure * ($rules->pressure_blockage_threshold_multiplier ?? 0.7);

        // Check for leak (pressure increase)
        if ($latestPressure->value > $leakThreshold) {
            // Check if alert already exists
            $existingAlert = Alert::where('sensor_id', $sensor->id)
                ->where('type', 'pressure_leak')
                ->where('handled', false)
                ->where('created_at', '>', now()->subMinutes(30))
                ->first();

            if (!$existingAlert) {
                Alert::create([
                    'zone_id' => $sensor->zone_id,
                    'sensor_id' => $sensor->id,
                    'level' => 'warning',
                    'type' => 'pressure_leak',
                    'message' => "Pressure increased significantly ({$latestPressure->value} PSI vs avg {$avgPressure} PSI). Possible leak detected.",
                    'handled' => false,
                ]);

                Log::warning("Rule Engine: Pressure leak detected for sensor {$sensor->id} ({$latestPressure->value} PSI)");
            }
        }

        // Check for blockage (pressure decrease)
        if ($latestPressure->value < $blockageThreshold) {
            // Check if alert already exists
            $existingAlert = Alert::where('sensor_id', $sensor->id)
                ->where('type', 'pressure_blockage')
                ->where('handled', false)
                ->where('created_at', '>', now()->subMinutes(30))
                ->first();

            if (!$existingAlert) {
                Alert::create([
                    'zone_id' => $sensor->zone_id,
                    'sensor_id' => $sensor->id,
                    'level' => 'warning',
                    'type' => 'pressure_blockage',
                    'message' => "Pressure decreased significantly ({$latestPressure->value} PSI vs avg {$avgPressure} PSI). Possible blockage detected.",
                    'handled' => false,
                ]);

                Log::warning("Rule Engine: Pressure blockage detected for sensor {$sensor->id} ({$latestPressure->value} PSI)");
            }
        }
    }

    /**
     * Rule: Battery level monitoring → alert when battery is low
     */
    private function checkBatteryLevel(Sensor $sensor, ZoneRule $rules): void
    {
        // Get battery level from telemetry or sensor model
        $latestBattery = $sensor->getLatestTelemetry('battery_level');
        $batteryLevel = $latestBattery ? $latestBattery->value : $sensor->battery_level;

        if ($batteryLevel === null) {
            return; // No battery data
        }

        $threshold = $rules->battery_low_threshold ?? 20.0;

        if ($batteryLevel < $threshold) {
            // Check if alert already exists
            $existingAlert = Alert::where('sensor_id', $sensor->id)
                ->where('type', 'low_battery')
                ->where('handled', false)
                ->where('created_at', '>', now()->subHours(6))
                ->first();

            if (!$existingAlert) {
                Alert::create([
                    'zone_id' => $sensor->zone_id,
                    'sensor_id' => $sensor->id,
                    'level' => 'warning',
                    'type' => 'low_battery',
                    'message' => "Sensor battery is low ({$batteryLevel}% < {$threshold}%). Please replace battery soon.",
                    'handled' => false,
                ]);

                Log::warning("Rule Engine: Low battery detected for sensor {$sensor->id} ({$batteryLevel}%)");
            }
        }
    }

    /**
     * Rule: Stuck sensor detection → alert when sensor value hasn't changed for a long time
     */
    private function checkStuckSensor(Sensor $sensor, ZoneRule $rules): void
    {
        $timeoutMinutes = $rules->stuck_sensor_timeout_minutes ?? 30;
        $timeoutTime = now()->subMinutes($timeoutMinutes);

        // Get latest telemetry
        $latestTelemetry = $sensor->telemetry()
            ->where('recorded_at', '>=', $timeoutTime)
            ->orderBy('recorded_at', 'desc')
            ->get();

        if ($latestTelemetry->isEmpty()) {
            return; // No recent data
        }

        // Group by metric and check if values are stuck
        $metrics = $latestTelemetry->groupBy('metric');

        foreach ($metrics as $metric => $records) {
            if ($records->count() < 5) {
                continue; // Need at least 5 readings
            }

            // Check if all values are the same (or very close)
            $values = $records->pluck('value')->unique();
            
            // Allow small variations (0.1% for percentages, 0.01 for other metrics)
            $tolerance = in_array($metric, ['moisture', 'battery_level']) ? 0.1 : 0.01;
            $minValue = $records->min('value');
            $maxValue = $records->max('value');
            
            if (($maxValue - $minValue) <= $tolerance) {
                // Sensor is stuck - check if alert already exists
                $existingAlert = Alert::where('sensor_id', $sensor->id)
                    ->where('type', 'stuck_sensor')
                    ->where('handled', false)
                    ->where('created_at', '>', now()->subHours(2))
                    ->first();

                if (!$existingAlert) {
                    Alert::create([
                        'zone_id' => $sensor->zone_id,
                        'sensor_id' => $sensor->id,
                        'level' => 'warning',
                        'type' => 'stuck_sensor',
                        'message' => "Sensor '{$sensor->name}' ({$metric}) appears stuck at value {$minValue} for over {$timeoutMinutes} minutes. Please check sensor.",
                        'handled' => false,
                    ]);

                    Log::warning("Rule Engine: Stuck sensor detected for sensor {$sensor->id} ({$metric} = {$minValue})");
                }
            }
        }
    }
}

