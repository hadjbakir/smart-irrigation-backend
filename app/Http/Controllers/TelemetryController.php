<?php

namespace App\Http\Controllers;

use App\Models\Sensor;
use App\Models\Telemetry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TelemetryController extends Controller
{
    /**
     * Get latest telemetry for all sensors (OPTIMIZED)
     */
    public function latest(): JsonResponse
    {
        try {
            // Get all sensors with zone in one query
            $sensors = Sensor::with('zone')->get();
            
            if ($sensors->isEmpty()) {
                return response()->json([]);
            }

            $sensorIds = $sensors->pluck('id')->toArray();
            $metrics = ['moisture', 'current', 'flow', 'pump_status', 'valve_status'];

            // Get latest telemetry - optimized: one query per metric (much faster than N+1)
            $latestTelemetryData = [];
            
            // Process each metric separately for better performance and index usage
            foreach ($metrics as $metric) {
                // Get latest telemetry for this metric across all sensors
                // Use optimized query with proper parameter binding
                $records = Telemetry::select('sensor_id', 'value', 'recorded_at')
                    ->whereIn('sensor_id', $sensorIds)
                    ->where('metric', $metric)
                    ->whereRaw('(sensor_id, recorded_at) IN (
                        SELECT sensor_id, MAX(recorded_at)
                        FROM telemetry
                        WHERE sensor_id IN (' . implode(',', array_fill(0, count($sensorIds), '?')) . ')
                        AND metric = ?
                        GROUP BY sensor_id
                    )', array_merge($sensorIds, [$metric]))
                    ->get();

                // Organize by sensor_id
                foreach ($records as $record) {
                    if (!isset($latestTelemetryData[$record->sensor_id])) {
                        $latestTelemetryData[$record->sensor_id] = [];
                    }
                    $latestTelemetryData[$record->sensor_id][$metric] = [
                        'value' => $record->value,
                        'recorded_at' => $record->recorded_at->toIso8601String(),
                    ];
                }
            }

            // Build result array
            $result = [];
            foreach ($sensors as $sensor) {
                $result[] = [
                    'sensor_id' => $sensor->id,
                    'sensor_name' => $sensor->name,
                    'sensor_type' => $sensor->type,
                    'zone_id' => $sensor->zone_id,
                    'zone_name' => $sensor->zone->name ?? null,
                    'telemetry' => $latestTelemetryData[$sensor->id] ?? [],
                ];
            }

            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error("TelemetryController::latest error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch telemetry'], 500);
        }
    }

    /**
     * Get historical telemetry data with enhanced filtering and aggregation
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $query = Telemetry::with('sensor.zone');

            // Filter by sensor_id (supports multiple)
            if ($request->has('sensor_id')) {
                $sensorIds = is_array($request->sensor_id) 
                    ? $request->sensor_id 
                    : explode(',', $request->sensor_id);
                $query->whereIn('sensor_id', $sensorIds);
            }

            // Filter by metric (supports multiple)
            if ($request->has('metric')) {
                $metrics = is_array($request->metric) 
                    ? $request->metric 
                    : explode(',', $request->metric);
                $query->whereIn('metric', $metrics);
            }

            // Filter by zone_id
            if ($request->has('zone_id')) {
                $zoneIds = is_array($request->zone_id) 
                    ? $request->zone_id 
                    : explode(',', $request->zone_id);
                $query->whereHas('sensor', function ($q) use ($zoneIds) {
                    $q->whereIn('zone_id', $zoneIds);
                });
            }

            // Filter by date range
            $from = $request->get('from');
            $to = $request->get('to');
            
            if ($from) {
                $query->where('recorded_at', '>=', Carbon::parse($from));
            } else {
                // Default to last 24 hours if no from date
                $query->where('recorded_at', '>=', Carbon::now()->subDay());
            }

            if ($to) {
                $query->where('recorded_at', '<=', Carbon::parse($to));
            }

            // Aggregation mode
            $groupBy = $request->get('group_by'); // 'hour', 'day', 'none'
            $aggregate = $request->get('aggregate', 'avg'); // 'avg', 'min', 'max', 'sum'

            if ($groupBy && $groupBy !== 'none') {
                // Build base query for aggregation
                $baseQuery = DB::table('telemetry');

                // Apply filters
                if ($request->has('sensor_id')) {
                    $sensorIds = is_array($request->sensor_id) 
                        ? $request->sensor_id 
                        : explode(',', $request->sensor_id);
                    $baseQuery->whereIn('sensor_id', $sensorIds);
                }

                if ($request->has('metric')) {
                    $metrics = is_array($request->metric) 
                        ? $request->metric 
                        : explode(',', $request->metric);
                    $baseQuery->whereIn('metric', $metrics);
                }

                if ($from) {
                    $baseQuery->where('recorded_at', '>=', Carbon::parse($from));
                } else {
                    $baseQuery->where('recorded_at', '>=', Carbon::now()->subDay());
                }

                if ($to) {
                    $baseQuery->where('recorded_at', '<=', Carbon::parse($to));
                }

                // Group by time interval
                $format = match($groupBy) {
                    'hour' => '%Y-%m-%d %H:00:00',
                    'day' => '%Y-%m-%d',
                    'week' => '%Y-%u',
                    'month' => '%Y-%m',
                    default => '%Y-%m-%d %H:00:00',
                };

                $telemetry = $baseQuery
                    ->select(
                        DB::raw("DATE_FORMAT(recorded_at, '{$format}') as time_group"),
                        'sensor_id',
                        'metric',
                        DB::raw("AVG(value) as avg_value"),
                        DB::raw("MIN(value) as min_value"),
                        DB::raw("MAX(value) as max_value"),
                        DB::raw("COUNT(*) as count")
                    )
                    ->groupBy('time_group', 'sensor_id', 'metric')
                    ->orderBy('time_group', 'asc')
                    ->get();

                // Get sensor names
                $sensorIds = $telemetry->pluck('sensor_id')->unique();
                $sensors = Sensor::whereIn('id', $sensorIds)->with('zone')->get()->keyBy('id');

                $result = $telemetry->map(function ($record) use ($sensors, $aggregate) {
                    $sensor = $sensors->get($record->sensor_id);
                    $value = match($aggregate) {
                        'min' => $record->min_value,
                        'max' => $record->max_value,
                        'sum' => $record->avg_value * $record->count, // Approximate sum
                        default => $record->avg_value,
                    };

                    return [
                        'sensor_id' => $record->sensor_id,
                        'sensor_name' => $sensor->name ?? null,
                        'zone_id' => $sensor->zone_id ?? null,
                        'zone_name' => $sensor->zone->name ?? null,
                        'metric' => $record->metric,
                        'value' => round($value, 2),
                        'min_value' => round($record->min_value, 2),
                        'max_value' => round($record->max_value, 2),
                        'count' => $record->count,
                        'recorded_at' => $record->time_group,
                        'time_group' => $record->time_group,
                    ];
                });

                return response()->json($result);
            }

            // No aggregation - return raw data
            $limit = $request->get('limit', 1000);
            $query->limit($limit);
            $query->orderBy('recorded_at', $request->get('order', 'asc'));

            $telemetry = $query->get()->map(function ($record) {
                return [
                    'id' => $record->id,
                    'sensor_id' => $record->sensor_id,
                    'sensor_name' => $record->sensor->name ?? null,
                    'zone_id' => $record->sensor->zone_id ?? null,
                    'zone_name' => $record->sensor->zone->name ?? null,
                    'metric' => $record->metric,
                    'value' => $record->value,
                    'recorded_at' => $record->recorded_at->toIso8601String(),
                ];
            });

            return response()->json($telemetry);
        } catch (\Exception $e) {
            \Log::error("TelemetryController::history error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch historical telemetry', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Get statistics for telemetry data
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $query = Telemetry::query();

            // Filter by sensor_id
            if ($request->has('sensor_id')) {
                $sensorIds = is_array($request->sensor_id) 
                    ? $request->sensor_id 
                    : explode(',', $request->sensor_id);
                $query->whereIn('sensor_id', $sensorIds);
            }

            // Filter by metric
            if ($request->has('metric')) {
                $metrics = is_array($request->metric) 
                    ? $request->metric 
                    : explode(',', $request->metric);
                $query->whereIn('metric', $metrics);
            }

            // Filter by date range
            $from = $request->get('from');
            $to = $request->get('to');
            
            if ($from) {
                $query->where('recorded_at', '>=', Carbon::parse($from));
            } else {
                $query->where('recorded_at', '>=', Carbon::now()->subDay());
            }

            if ($to) {
                $query->where('recorded_at', '<=', Carbon::parse($to));
            }

            // Get statistics grouped by sensor and metric
            $stats = $query->select(
                'sensor_id',
                'metric',
                DB::raw('AVG(value) as avg_value'),
                DB::raw('MIN(value) as min_value'),
                DB::raw('MAX(value) as max_value'),
                DB::raw('COUNT(*) as count'),
                DB::raw('STDDEV(value) as std_dev')
            )
            ->groupBy('sensor_id', 'metric')
            ->get();

            // Get sensor names
            $sensorIds = $stats->pluck('sensor_id')->unique();
            $sensors = Sensor::whereIn('id', $sensorIds)->with('zone')->get()->keyBy('id');

            $result = $stats->map(function ($stat) use ($sensors) {
                $sensor = $sensors->get($stat->sensor_id);
                return [
                    'sensor_id' => $stat->sensor_id,
                    'sensor_name' => $sensor->name ?? null,
                    'zone_id' => $sensor->zone_id ?? null,
                    'zone_name' => $sensor->zone->name ?? null,
                    'metric' => $stat->metric,
                    'avg' => round($stat->avg_value, 2),
                    'min' => round($stat->min_value, 2),
                    'max' => round($stat->max_value, 2),
                    'count' => $stat->count,
                    'std_dev' => round($stat->std_dev ?? 0, 2),
                ];
            });

            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error("TelemetryController::statistics error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch statistics', 'details' => $e->getMessage()], 500);
        }
    }
}
