<?php

namespace App\Http\Controllers;

use App\Services\WeatherService;
use App\Services\RuleEngine;
use App\Models\Weather;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WeatherController extends Controller
{
    /**
     * Get current weather
     */
    public function current(Request $request, WeatherService $weatherService): JsonResponse
    {
        $latitude = $request->get('lat', config('app.weather_latitude', 0));
        $longitude = $request->get('lon', config('app.weather_longitude', 0));

        if ($latitude == 0 || $longitude == 0) {
            return response()->json(['error' => 'Latitude and longitude required'], 400);
        }

        try {
            // Try to get latest from database first
            $latest = $weatherService->getLatestWeather($latitude, $longitude);
            
            // If data is older than 10 minutes, fetch fresh data
            if (!$latest || $latest->recorded_at->diffInMinutes(now()) > 10) {
                $weatherData = $weatherService->fetchCurrentWeather($latitude, $longitude);
                
                if ($weatherData) {
                    $latest = Weather::create([
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'temperature' => $weatherData['temperature'],
                        'humidity' => $weatherData['humidity'],
                        'pressure' => $weatherData['pressure'],
                        'wind_speed' => $weatherData['wind_speed'],
                        'condition' => $weatherData['condition'],
                        'description' => $weatherData['description'],
                        'is_raining' => $weatherData['is_raining'],
                        'rain_amount' => $weatherData['rain_1h'] ?? $weatherData['rain_3h'] ?? 0,
                        'data' => $weatherData,
                        'recorded_at' => \Carbon\Carbon::createFromTimestamp($weatherData['timestamp']),
                    ]);
                }
            }

            if (!$latest) {
                return response()->json(['error' => 'Failed to fetch weather data'], 500);
            }

            return response()->json([
                'temperature' => $latest->temperature,
                'humidity' => $latest->humidity,
                'pressure' => $latest->pressure,
                'wind_speed' => $latest->wind_speed,
                'condition' => $latest->condition,
                'description' => $latest->description,
                'is_raining' => $latest->is_raining,
                'rain_amount' => $latest->rain_amount,
                'recorded_at' => $latest->recorded_at->toIso8601String(),
                'location' => [
                    'latitude' => $latest->latitude,
                    'longitude' => $latest->longitude,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('WeatherController::current error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch weather', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Get weather forecast
     */
    public function forecast(Request $request, WeatherService $weatherService): JsonResponse
    {
        $latitude = $request->get('lat', config('app.weather_latitude', 0));
        $longitude = $request->get('lon', config('app.weather_longitude', 0));
        $days = $request->get('days', 5);

        if ($latitude == 0 || $longitude == 0) {
            return response()->json(['error' => 'Latitude and longitude required'], 400);
        }

        try {
            $forecastData = $weatherService->fetchForecast($latitude, $longitude, $days);
            
            if (!$forecastData) {
                return response()->json(['error' => 'Failed to fetch forecast'], 500);
            }

            return response()->json($forecastData);
        } catch (\Exception $e) {
            Log::error('WeatherController::forecast error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch forecast', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Check weather and trigger alerts if raining
     */
    public function check(Request $request, WeatherService $weatherService, RuleEngine $ruleEngine): JsonResponse
    {
        $latitude = $request->get('lat', config('app.weather_latitude', 0));
        $longitude = $request->get('lon', config('app.weather_longitude', 0));

        if ($latitude == 0 || $longitude == 0) {
            return response()->json(['error' => 'Latitude and longitude required'], 400);
        }

        try {
            $weatherService->checkWeatherAndAlert($latitude, $longitude, $ruleEngine);
            
            return response()->json([
                'message' => 'Weather checked successfully',
                'location' => ['latitude' => $latitude, 'longitude' => $longitude],
            ]);
        } catch (\Exception $e) {
            Log::error('WeatherController::check error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to check weather', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Get weather history
     */
    public function history(Request $request): JsonResponse
    {
        $latitude = $request->get('lat');
        $longitude = $request->get('lon');
        $from = $request->get('from');
        $to = $request->get('to');
        $limit = $request->get('limit', 100);

        $query = Weather::query();

        if ($latitude) {
            $query->where('latitude', $latitude);
        }

        if ($longitude) {
            $query->where('longitude', $longitude);
        }

        if ($from) {
            $query->where('recorded_at', '>=', $from);
        }

        if ($to) {
            $query->where('recorded_at', '<=', $to);
        }

        $weather = $query->orderBy('recorded_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'temperature' => $record->temperature,
                    'humidity' => $record->humidity,
                    'pressure' => $record->pressure,
                    'wind_speed' => $record->wind_speed,
                    'condition' => $record->condition,
                    'description' => $record->description,
                    'is_raining' => $record->is_raining,
                    'rain_amount' => $record->rain_amount,
                    'recorded_at' => $record->recorded_at->toIso8601String(),
                    'location' => [
                        'latitude' => $record->latitude,
                        'longitude' => $record->longitude,
                    ],
                ];
            });

        return response()->json($weather);
    }
}
