<?php

namespace App\Http\Controllers;

use App\Models\Sensor;
use App\Models\Telemetry;
use App\Services\RuleEngine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SimulateController extends Controller
{
    /**
     * Inject anomaly simulation
     */
    public function simulate(Request $request, RuleEngine $ruleEngine): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:leak,overload,rain,sensor_failure',
            'sensor_id' => 'required_if:type,leak,overload,sensor_failure|exists:sensors,id',
            'zone_id' => 'required_if:type,rain|exists:zones,id',
        ]);

        $type = $validated['type'];
        $sensor = null;
        $zoneId = $validated['zone_id'] ?? null;

        if (isset($validated['sensor_id'])) {
            $sensor = Sensor::find($validated['sensor_id']);
        }

        switch ($type) {
            case 'leak':
                $this->simulateLeak($sensor);
                break;
            case 'overload':
                $this->simulateOverload($sensor);
                break;
            case 'rain':
                $ruleEngine->handleRainForecast($zoneId);
                break;
            case 'sensor_failure':
                $this->simulateSensorFailure($sensor);
                break;
        }

        return response()->json([
            'status' => 'ok',
            'message' => "Anomaly '{$type}' simulated successfully",
        ]);
    }

    /**
     * Simulate leak (high flow rate)
     */
    private function simulateLeak(Sensor $sensor): void
    {
        if ($sensor->type !== 'flow') {
            return;
        }

        $expectedFlow = $sensor->meta['expected_flow'] ?? 10.0;
        $leakFlow = $expectedFlow * 2.5; // 2.5x normal flow

        Telemetry::create([
            'sensor_id' => $sensor->id,
            'metric' => 'flow',
            'value' => $leakFlow,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Simulate pump overload (high current)
     */
    private function simulateOverload(Sensor $sensor): void
    {
        if ($sensor->type !== 'pump') {
            return;
        }

        $overloadCurrent = 18.0; // Above threshold

        Telemetry::create([
            'sensor_id' => $sensor->id,
            'metric' => 'current',
            'value' => $overloadCurrent,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Simulate sensor failure (no data)
     */
    private function simulateSensorFailure(Sensor $sensor): void
    {
        // In a real system, this would mark the sensor as offline
        // For now, we just log it
        \Log::warning("Sensor {$sensor->id} simulated failure");
    }
}












