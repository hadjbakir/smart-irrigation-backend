<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AlertController extends Controller
{
    /**
     * List all alerts
     */
    public function index(Request $request): JsonResponse
    {
        $query = Alert::with(['zone', 'sensor']);

        // Filter by level
        if ($request->has('level')) {
            $query->byLevel($request->level);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by handled status
        if ($request->has('handled')) {
            $query->where('handled', filter_var($request->handled, FILTER_VALIDATE_BOOLEAN));
        } else {
            // Default to unhandled alerts
            $query->unhandled();
        }

        // Filter by zone_id
        if ($request->has('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        // Filter by sensor_id
        if ($request->has('sensor_id')) {
            $query->where('sensor_id', $request->sensor_id);
        }

        $alerts = $query->latest()->limit(100)->get();

        return response()->json($alerts);
    }

    /**
     * Get alert details
     */
    public function show($id): JsonResponse
    {
        $alert = Alert::with(['zone', 'sensor'])->find($id);

        if (!$alert) {
            return response()->json(['error' => 'Alert not found'], 404);
        }

        return response()->json($alert);
    }

    /**
     * Mark alert as handled
     */
    public function handle($id): JsonResponse
    {
        $alert = Alert::find($id);

        if (!$alert) {
            return response()->json(['error' => 'Alert not found'], 404);
        }

        $alert->update(['handled' => true]);

        return response()->json($alert);
    }
}












