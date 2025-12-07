<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ZoneController extends Controller
{
    /**
     * List all zones with sensors
     */
    public function index(): JsonResponse
    {
        $zones = Zone::with('sensors')->get();
        return response()->json($zones);
    }

    /**
     * Get zone details
     */
    public function show($id): JsonResponse
    {
        $zone = Zone::with(['sensors', 'alerts' => function ($query) {
            $query->latest()->limit(10);
        }])->find($id);

        if (!$zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        return response()->json($zone);
    }

    /**
     * Create new zone
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $zone = Zone::create($validated);

        return response()->json($zone, 201);
    }

    /**
     * Update zone (OPTIMIZED)
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $zone = Zone::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'notes' => 'nullable|string',
                'mode' => 'sometimes|in:auto,manual',
                'manual_notes' => 'nullable|string',
            ]);

            $zone->update($validated);

            return response()->json($zone);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Zone not found'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error("ZoneController::update error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to update zone'], 500);
        }
    }

    /**
     * Delete zone
     */
    public function destroy($id): JsonResponse
    {
        $zone = Zone::find($id);

        if (!$zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        $zone->delete();

        return response()->json(['message' => 'Zone deleted successfully']);
    }
}






