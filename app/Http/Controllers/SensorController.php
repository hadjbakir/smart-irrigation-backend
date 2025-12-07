<?php

namespace App\Http\Controllers;

use App\Models\Sensor;
use App\Models\Action;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SensorController extends Controller
{
    /**
     * List all sensors
     */
    public function index(): JsonResponse
    {
        $sensors = Sensor::with('zone')->get();
        return response()->json($sensors);
    }

    /**
     * Get sensor details
     */
    public function show($id): JsonResponse
    {
        $sensor = Sensor::with(['zone', 'telemetry' => function ($query) {
            $query->latest('recorded_at')->limit(10);
        }])->find($id);

        if (!$sensor) {
            return response()->json(['error' => 'Sensor not found'], 404);
        }

        return response()->json($sensor);
    }

    /**
     * Create new sensor
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zone_id' => 'required|exists:zones,id',
            'type' => 'required|in:soil_sensor,pump,flow,valve',
            'name' => 'nullable|string|max:255',
            'meta' => 'nullable|array',
        ]);

        $sensor = Sensor::create($validated);

        return response()->json($sensor, 201);
    }

    /**
     * Update sensor
     */
    public function update(Request $request, $id): JsonResponse
    {
        $sensor = Sensor::find($id);

        if (!$sensor) {
            return response()->json(['error' => 'Sensor not found'], 404);
        }

        $validated = $request->validate([
            'zone_id' => 'sometimes|exists:zones,id',
            'type' => 'sometimes|in:soil_sensor,pump,flow,valve',
            'name' => 'nullable|string|max:255',
            'meta' => 'nullable|array',
        ]);

        $sensor->update($validated);

        return response()->json($sensor);
    }

    /**
     * Delete sensor
     */
    public function destroy($id): JsonResponse
    {
        $sensor = Sensor::find($id);

        if (!$sensor) {
            return response()->json(['error' => 'Sensor not found'], 404);
        }

        $sensor->delete();

        return response()->json(['message' => 'Sensor deleted successfully']);
    }

    /**
     * Manual pump control
     */
    public function controlPump($id, $action): JsonResponse
    {
        $sensor = Sensor::find($id);

        if (!$sensor) {
            return response()->json(['error' => 'Sensor not found'], 404);
        }

        if ($sensor->type !== 'pump') {
            return response()->json(['error' => 'Sensor is not a pump'], 400);
        }

        $actionType = strtolower($action) === 'on' ? 'pump_on' : 'pump_off';

        // Create action
        Action::create([
            'sensor_id' => $sensor->id,
            'action' => $actionType,
            'payload' => [
                'triggered_by' => 'manual',
                'user_request' => true,
            ],
            'status' => 'pending',
            'requested_at' => now(),
            'max_retries' => 3,
        ]);

        return response()->json([
            'status' => 'ok',
            'message' => "Pump {$action} command queued",
        ]);
    }
}
