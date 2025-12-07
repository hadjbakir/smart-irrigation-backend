<?php

namespace App\Services;

use App\Models\Weather;
use App\Models\Zone;
use App\Models\Alert;
use App\Services\RuleEngine;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class WeatherService
{
    private const API_BASE_URL = 'https://api.openweathermap.org/data/2.5';
    private const CACHE_TTL = 600; // 10 minutes cache

    /**
     * Fetch current weather for a location
     */
    public function fetchCurrentWeather(float $latitude, float $longitude): ?array
    {
        $apiKey = config('services.openweathermap.api_key');
        
        if (!$apiKey) {
            Log::warning('OpenWeatherMap API key not configured');
            return null;
        }

        $cacheKey = "weather_{$latitude}_{$longitude}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($latitude, $longitude, $apiKey) {
            try {
                $response = Http::timeout(10)->get(self::API_BASE_URL . '/weather', [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'appid' => $apiKey,
                    'units' => 'metric',
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $this->parseWeatherData($data);
                }

                Log::error('OpenWeatherMap API error: ' . $response->body());
                return null;
            } catch (\Exception $e) {
                Log::error('Weather API request failed: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Fetch weather forecast for a location
     */
    public function fetchForecast(float $latitude, float $longitude, int $days = 5): ?array
    {
        $apiKey = config('services.openweathermap.api_key');
        
        if (!$apiKey) {
            Log::warning('OpenWeatherMap API key not configured');
            return null;
        }

        try {
            $response = Http::timeout(10)->get(self::API_BASE_URL . '/forecast', [
                'lat' => $latitude,
                'lon' => $longitude,
                'appid' => $apiKey,
                'units' => 'metric',
                'cnt' => $days * 8, // 8 forecasts per day (3-hour intervals)
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->parseForecastData($data);
            }

            Log::error('OpenWeatherMap Forecast API error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Weather Forecast API request failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse weather API response
     */
    private function parseWeatherData(array $data): array
    {
        $weather = $data['weather'][0] ?? [];
        $main = $data['main'] ?? [];
        $wind = $data['wind'] ?? [];
        $rain = $data['rain'] ?? [];
        $snow = $data['snow'] ?? [];

        return [
            'temperature' => $main['temp'] ?? null,
            'feels_like' => $main['feels_like'] ?? null,
            'humidity' => $main['humidity'] ?? null,
            'pressure' => $main['pressure'] ?? null,
            'wind_speed' => $wind['speed'] ?? null,
            'wind_direction' => $wind['deg'] ?? null,
            'clouds' => $data['clouds']['all'] ?? null,
            'visibility' => $data['visibility'] ?? null,
            'condition' => $weather['main'] ?? null,
            'description' => $weather['description'] ?? null,
            'icon' => $weather['icon'] ?? null,
            'rain_1h' => $rain['1h'] ?? 0,
            'rain_3h' => $rain['3h'] ?? 0,
            'snow_1h' => $snow['1h'] ?? 0,
            'snow_3h' => $snow['3h'] ?? 0,
            'is_raining' => $this->isRaining($weather['main'] ?? '', $rain),
            'timestamp' => $data['dt'] ?? time(),
            'location' => [
                'name' => $data['name'] ?? null,
                'country' => $data['sys']['country'] ?? null,
                'lat' => $data['coord']['lat'] ?? null,
                'lon' => $data['coord']['lon'] ?? null,
            ],
        ];
    }

    /**
     * Parse forecast data
     */
    private function parseForecastData(array $data): array
    {
        $forecasts = [];
        
        foreach ($data['list'] ?? [] as $item) {
            $weather = $item['weather'][0] ?? [];
            $main = $item['main'] ?? [];
            $rain = $item['rain'] ?? [];
            $snow = $item['snow'] ?? [];

            $forecasts[] = [
                'timestamp' => $item['dt'],
                'datetime' => Carbon::createFromTimestamp($item['dt'])->toIso8601String(),
                'temperature' => $main['temp'] ?? null,
                'feels_like' => $main['feels_like'] ?? null,
                'humidity' => $main['humidity'] ?? null,
                'pressure' => $main['pressure'] ?? null,
                'condition' => $weather['main'] ?? null,
                'description' => $weather['description'] ?? null,
                'icon' => $weather['icon'] ?? null,
                'rain_3h' => $rain['3h'] ?? 0,
                'snow_3h' => $snow['3h'] ?? 0,
                'is_raining' => $this->isRaining($weather['main'] ?? '', $rain),
                'wind_speed' => $item['wind']['speed'] ?? null,
                'clouds' => $item['clouds']['all'] ?? null,
            ];
        }

        return [
            'location' => [
                'name' => $data['city']['name'] ?? null,
                'country' => $data['city']['country'] ?? null,
                'lat' => $data['city']['coord']['lat'] ?? null,
                'lon' => $data['city']['coord']['lon'] ?? null,
            ],
            'forecasts' => $forecasts,
        ];
    }

    /**
     * Check if it's currently raining
     */
    private function isRaining(string $condition, array $rain): bool
    {
        // Check if condition indicates rain
        $rainConditions = ['Rain', 'Drizzle', 'Thunderstorm'];
        if (in_array($condition, $rainConditions)) {
            return true;
        }

        // Check if rain amount > 0
        if (($rain['1h'] ?? 0) > 0 || ($rain['3h'] ?? 0) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Check weather and create alerts if raining
     */
    public function checkWeatherAndAlert(float $latitude, float $longitude, RuleEngine $ruleEngine): void
    {
        $weatherData = $this->fetchCurrentWeather($latitude, $longitude);

        if (!$weatherData) {
            Log::warning('Failed to fetch weather data');
            return;
        }

        // Store weather data
        Weather::create([
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
            'recorded_at' => Carbon::createFromTimestamp($weatherData['timestamp']),
        ]);

        // If it's raining, trigger rain alerts for all zones
        if ($weatherData['is_raining']) {
            $this->handleRainDetected($ruleEngine, $weatherData);
        }
    }

    /**
     * Handle rain detection - create alerts and postpone irrigation
     */
    private function handleRainDetected(RuleEngine $ruleEngine, array $weatherData): void
    {
        $zones = Zone::with('rules')->get();
        $rainAmount = $weatherData['rain_1h'] ?? $weatherData['rain_3h'] ?? 0;
        $description = $weatherData['description'] ?? 'rain';

        foreach ($zones as $zone) {
            // Skip manual mode zones if rain forecast is disabled
            $rules = $zone->rules ?? \App\Models\ZoneRule::make(\App\Models\ZoneRule::getDefaults());
            if (!$rules->enable_rain_forecast) {
                continue;
            }

            // Trigger rule engine rain forecast handler
            $ruleEngine->handleRainForecast($zone->id);

            // Create detailed rain alert
            Alert::create([
                'zone_id' => $zone->id,
                'level' => 'info',
                'type' => 'rain_detected',
                'message' => "Rain detected: {$description}. Rain amount: {$rainAmount}mm. Irrigation postponed for zone '{$zone->name}'.",
                'handled' => false,
            ]);

            Log::info("Rain detected for zone {$zone->id}: {$description} ({$rainAmount}mm)");
        }
    }

    /**
     * Get latest weather data
     */
    public function getLatestWeather(float $latitude, float $longitude): ?Weather
    {
        return Weather::where('latitude', $latitude)
            ->where('longitude', $longitude)
            ->latest('recorded_at')
            ->first();
    }
}




