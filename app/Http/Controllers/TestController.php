<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Sensor;
use App\Models\Action;
use App\Models\Zone;
use App\Models\Telemetry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TestController extends Controller
{
    /**
     * Test: Pump max runtime exceeded
     */
    public function testPumpMaxRuntime(Request $request): JsonResponse
    {
        $zoneId = $request->input('zone_id');
        $zone = Zone::find($zoneId);
        
        if (!$zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        $pumpSensor = Sensor::where('zone_id', $zoneId)->where('type', 'pump')->first();
        
        if (!$pumpSensor) {
            return response()->json(['error' => 'No pump sensor found in this zone'], 404);
        }

        // Set pump_started_at to simulate long runtime
        $pumpSensor->update(['pump_started_at' => now()->subHours(2)]);

        // Create telemetry showing pump is ON
        Telemetry::create([
            'sensor_id' => $pumpSensor->id,
            'metric' => 'pump_status',
            'value' => 1,
            'recorded_at' => now(),
        ]);

        // Create the alert
        Alert::create([
            'zone_id' => $zoneId,
            'sensor_id' => $pumpSensor->id,
            'level' => 'warning',
            'type' => 'pump_max_runtime',
            'message' => "Pump has been running for 120 minutes (max: 60 min). Auto-shutting down to prevent overload.",
            'handled' => false,
        ]);

        return response()->json([
            'message' => 'Pump max runtime test alert created',
            'zone' => $zone->name,
            'sensor' => $pumpSensor->name,
        ]);
    }

    /**
     * Test: Valve max runtime exceeded
     */
    public function testValveMaxRuntime(Request $request): JsonResponse
    {
        $zoneId = $request->input('zone_id');
        $zone = Zone::find($zoneId);
        
        if (!$zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        $valveSensor = Sensor::where('zone_id', $zoneId)->where('type', 'valve')->first();
        
        if (!$valveSensor) {
            return response()->json(['error' => 'No valve sensor found in this zone'], 404);
        }

        // Set valve_opened_at to simulate long runtime
        $valveSensor->update(['valve_opened_at' => now()->subHours(3)]);

        // Create telemetry showing valve is OPEN
        Telemetry::create([
            'sensor_id' => $valveSensor->id,
            'metric' => 'valve_status',
            'value' => 1,
            'recorded_at' => now(),
        ]);

        // Create the alert
        Alert::create([
            'zone_id' => $zoneId,
            'sensor_id' => $valveSensor->id,
            'level' => 'warning',
            'type' => 'valve_max_runtime',
            'message' => "Valve has been open for 180 minutes (max: 120 min). Auto-closing.",
            'handled' => false,
        ]);

        return response()->json([
            'message' => 'Valve max runtime test alert created',
            'zone' => $zone->name,
            'sensor' => $valveSensor->name,
        ]);
    }

    /**
     * Test: Action execution failed after retries
     */
    public function testActionExecutionFailed(Request $request): JsonResponse
    {
        $zoneId = $request->input('zone_id');
        $zone = Zone::find($zoneId);
        
        if (!$zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        $pumpSensor = Sensor::where('zone_id', $zoneId)->where('type', 'pump')->first();
        
        if (!$pumpSensor) {
            return response()->json(['error' => 'No pump sensor found in this zone'], 404);
        }

        // Create a failed action with max retries
        $action = Action::create([
            'sensor_id' => $pumpSensor->id,
            'action' => 'pump_off',
            'payload' => [
                'triggered_by' => 'test',
                'test' => true,
            ],
            'status' => 'failed',
            'requested_at' => now()->subMinutes(5),
            'retry_count' => 3,
            'max_retries' => 3,
        ]);

        // Create the critical alert
        Alert::create([
            'zone_id' => $zoneId,
            'sensor_id' => $pumpSensor->id,
            'level' => 'critical',
            'type' => 'action_execution_failed',
            'message' => "Failed to execute pump_off for sensor '{$pumpSensor->name}' after 3 attempts. Please check hardware.",
            'handled' => false,
        ]);

        return response()->json([
            'message' => 'Action execution failed test alert created',
            'zone' => $zone->name,
            'sensor' => $pumpSensor->name,
            'action_id' => $action->id,
        ]);
    }

    /**
     * Test: Pressure leak detected
     */
    public function testPressureLeak(Request $request): JsonResponse
    {
        $zoneId = $request->input('zone_id');
        $zone = Zone::find($zoneId);
        
        if (!$zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        $pressureSensor = Sensor::where('zone_id', $zoneId)->where('type', 'pressure_sensor')->first();
        
        if (!$pressureSensor) {
            return response()->json(['error' => 'No pressure sensor found in this zone'], 404);
        }

        // Create high pressure telemetry
        Telemetry::create([
            'sensor_id' => $pressureSensor->id,
            'metric' => 'pressure',
            'value' => 85.0, // High pressure
            'recorded_at' => now(),
        ]);

        // Create the alert
        Alert::create([
            'zone_id' => $zoneId,
            'sensor_id' => $pressureSensor->id,
            'level' => 'warning',
            'type' => 'pressure_leak',
            'message' => "Pressure increased significantly (85.0 PSI vs avg 50.0 PSI). Possible leak detected.",
            'handled' => false,
        ]);

        return response()->json([
            'message' => 'Pressure leak test alert created',
            'zone' => $zone->name,
            'sensor' => $pressureSensor->name,
        ]);
    }

    /**
     * Test: Pressure blockage detected
     */
    public function testPressureBlockage(Request $request): JsonResponse
    {
        $zoneId = $request->input('zone_id');
        $zone = Zone::find($zoneId);
        
        if (!$zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        $pressureSensor = Sensor::where('zone_id', $zoneId)->where('type', 'pressure_sensor')->first();
        
        if (!$pressureSensor) {
            return response()->json(['error' => 'No pressure sensor found in this zone'], 404);
        }

        // Create low pressure telemetry
        Telemetry::create([
            'sensor_id' => $pressureSensor->id,
            'metric' => 'pressure',
            'value' => 20.0, // Low pressure
            'recorded_at' => now(),
        ]);

        // Create the alert
        Alert::create([
            'zone_id' => $zoneId,
            'sensor_id' => $pressureSensor->id,
            'level' => 'warning',
            'type' => 'pressure_blockage',
            'message' => "Pressure decreased significantly (20.0 PSI vs avg 50.0 PSI). Possible blockage detected.",
            'handled' => false,
        ]);

        return response()->json([
            'message' => 'Pressure blockage test alert created',
            'zone' => $zone->name,
            'sensor' => $pressureSensor->name,
        ]);
    }

    /**
     * Test: Low battery
     */
    public function testLowBattery(Request $request): JsonResponse
    {
        $zoneId = $request->input('zone_id');
        $zone = Zone::find($zoneId);
        
        if (!$zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        $sensor = Sensor::where('zone_id', $zoneId)->first();
        
        if (!$sensor) {
            return response()->json(['error' => 'No sensor found in this zone'], 404);
        }

        // Set low battery level
        $sensor->update([
            'battery_level' => 15.0,
            'last_battery_update' => now(),
        ]);

        // Create battery telemetry
        Telemetry::create([
            'sensor_id' => $sensor->id,
            'metric' => 'battery_level',
            'value' => 15.0,
            'recorded_at' => now(),
        ]);

        // Create the alert
        Alert::create([
            'zone_id' => $zoneId,
            'sensor_id' => $sensor->id,
            'level' => 'warning',
            'type' => 'low_battery',
            'message' => "Sensor battery is low (15.0% < 20.0%). Please replace battery soon.",
            'handled' => false,
        ]);

        return response()->json([
            'message' => 'Low battery test alert created',
            'zone' => $zone->name,
            'sensor' => $sensor->name,
        ]);
    }

    /**
     * Test: Stuck sensor
     */
    public function testStuckSensor(Request $request): JsonResponse
    {
        $zoneId = $request->input('zone_id');
        $zone = Zone::find($zoneId);
        
        if (!$zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        $sensor = Sensor::where('zone_id', $zoneId)->where('type', 'soil_sensor')->first();
        
        if (!$sensor) {
            $sensor = Sensor::where('zone_id', $zoneId)->first();
        }
        
        if (!$sensor) {
            return response()->json(['error' => 'No sensor found in this zone'], 404);
        }

        // Create multiple identical telemetry readings to simulate stuck sensor
        $stuckValue = 45.5;
        for ($i = 0; $i < 10; $i++) {
            Telemetry::create([
                'sensor_id' => $sensor->id,
                'metric' => 'moisture',
                'value' => $stuckValue,
                'recorded_at' => now()->subMinutes(35 - ($i * 2)), // Spread over 35 minutes
            ]);
        }

        // Create the alert
        Alert::create([
            'zone_id' => $zoneId,
            'sensor_id' => $sensor->id,
            'level' => 'warning',
            'type' => 'stuck_sensor',
            'message' => "Sensor '{$sensor->name}' (moisture) appears stuck at value {$stuckValue} for over 30 minutes. Please check sensor.",
            'handled' => false,
        ]);

        return response()->json([
            'message' => 'Stuck sensor test alert created',
            'zone' => $zone->name,
            'sensor' => $sensor->name,
        ]);
    }

    /**
     * Test: Pump overload
     */
    public function testPumpOverload(Request $request): JsonResponse
    {
        $zoneId = $request->input('zone_id');
        $zone = Zone::find($zoneId);
        
        if (!$zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        $pumpSensor = Sensor::where('zone_id', $zoneId)->where('type', 'pump')->first();
        
        if (!$pumpSensor) {
            return response()->json(['error' => 'No pump sensor found in this zone'], 404);
        }

        // Create high current telemetry
        Telemetry::create([
            'sensor_id' => $pumpSensor->id,
            'metric' => 'current',
            'value' => 18.5, // Overload current
            'recorded_at' => now(),
        ]);

        // Create the critical alert
        Alert::create([
            'zone_id' => $zoneId,
            'sensor_id' => $pumpSensor->id,
            'level' => 'critical',
            'type' => 'pump_overload',
            'message' => "Pump overload detected! Current: 18.5A exceeds threshold: 15.0A. Emergency shutdown initiated.",
            'handled' => false,
        ]);

        return response()->json([
            'message' => 'Pump overload test alert created',
            'zone' => $zone->name,
            'sensor' => $pumpSensor->name,
        ]);
    }

    /**
     * Test: Leak detection
     */
    public function testLeakDetection(Request $request): JsonResponse
    {
        $zoneId = $request->input('zone_id');
        $zone = Zone::find($zoneId);
        
        if (!$zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        $flowSensor = Sensor::where('zone_id', $zoneId)->where('type', 'flow')->first();
        
        if (!$flowSensor) {
            return response()->json(['error' => 'No flow sensor found in this zone'], 404);
        }

        // Create high flow telemetry
        Telemetry::create([
            'sensor_id' => $flowSensor->id,
            'metric' => 'flow',
            'value' => 25.0, // High flow indicating leak
            'recorded_at' => now(),
        ]);

        // Create the critical alert
        Alert::create([
            'zone_id' => $zoneId,
            'sensor_id' => $flowSensor->id,
            'level' => 'critical',
            'type' => 'leak_detection',
            'message' => "Leak detected! Flow rate: 25.0 L/min exceeds expected: 10.0 L/min. Emergency shutdown initiated.",
            'handled' => false,
        ]);

        return response()->json([
            'message' => 'Leak detection test alert created',
            'zone' => $zone->name,
            'sensor' => $flowSensor->name,
        ]);
    }
}
