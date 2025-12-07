<?php

namespace App\Console\Commands;

use App\Services\WeatherService;
use App\Services\RuleEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckWeather extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'weather:check {--lat=} {--lon=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check current weather and create alerts if raining';

    /**
     * Execute the console command.
     */
    public function handle(WeatherService $weatherService, RuleEngine $ruleEngine)
    {
        $this->info('Checking weather...');

        // Get location from options or config
        $latitude = $this->option('lat') ?: config('app.weather_latitude', 0);
        $longitude = $this->option('lon') ?: config('app.weather_longitude', 0);

        if ($latitude == 0 || $longitude == 0) {
            $this->error('Please provide latitude and longitude via --lat and --lon options or set in config/app.php');
            return 1;
        }

        try {
            $weatherService->checkWeatherAndAlert($latitude, $longitude, $ruleEngine);
            $this->info("Weather checked successfully for location ({$latitude}, {$longitude})");
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to check weather: ' . $e->getMessage());
            Log::error('Weather check failed: ' . $e->getMessage());
            return 1;
        }
    }
}
