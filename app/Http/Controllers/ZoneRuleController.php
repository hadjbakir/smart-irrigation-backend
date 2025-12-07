<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use App\Models\ZoneRule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ZoneRuleController extends Controller
{
    /**
     * Get rules for a zone (OPTIMIZED)
     */
    public function show($zoneId): JsonResponse
    {
        try {
            // Use findOrFail for automatic 404 handling
            $zone = Zone::with('rules')->findOrFail($zoneId);

            // Return rules or defaults
            $rules = $zone->rules;
            if (!$rules) {
                $defaults = ZoneRule::getDefaults();
                $defaults['zone_id'] = $zone->id;
                $rules = ZoneRule::make($defaults);
            }

            return response()->json([
                'zone_id' => $zone->id,
                'zone_name' => $zone->name,
                'mode' => $zone->mode ?? 'auto',
                'rules' => $rules,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Zone not found'], 404);
        } catch (\Exception $e) {
            \Log::error("ZoneRuleController::show error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch zone rules'], 500);
        }
    }

    /**
     * Create or update rules for a zone (OPTIMIZED)
     */
    public function update(Request $request, $zoneId): JsonResponse
    {
        try {
            $zone = Zone::find($zoneId);

            if (!$zone) {
                return response()->json(['error' => 'Zone not found'], 404);
            }

            $validated = $request->validate([
                'moisture_threshold' => 'sometimes|numeric|min:0|max:100',
                'moisture_target' => 'sometimes|numeric|min:0|max:100',
                'pump_overload_current' => 'sometimes|numeric|min:0|max:50',
                'flow_leak_multiplier' => 'sometimes|numeric|min:1|max:5',
                'enable_low_moisture' => 'sometimes|boolean',
                'enable_pump_overload' => 'sometimes|boolean',
                'enable_leak_detection' => 'sometimes|boolean',
                'enable_rain_forecast' => 'sometimes|boolean',
                'irrigation_duration_minutes' => 'nullable|integer|min:1|max:1440',
                'schedule' => 'nullable|array',
            ]);

            // Use database transaction for atomicity
            $rules = DB::transaction(function () use ($zoneId, $validated) {
                return ZoneRule::updateOrCreate(
                    ['zone_id' => $zoneId],
                    $validated
                );
            });

            return response()->json([
                'message' => 'Zone rules updated successfully',
                'rules' => $rules,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error("ZoneRuleController::update error: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to update zone rules',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset rules to defaults
     */
    public function reset($zoneId): JsonResponse
    {
        $zone = Zone::find($zoneId);

        if (!$zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        $defaults = ZoneRule::getDefaults();
        $defaults['zone_id'] = $zoneId;

        $rules = ZoneRule::updateOrCreate(
            ['zone_id' => $zoneId],
            $defaults
        );

        return response()->json([
            'message' => 'Zone rules reset to defaults',
            'rules' => $rules,
        ]);
    }
}

