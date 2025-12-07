<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\Cors;
use App\Http\Controllers\TelemetryController;
use App\Http\Controllers\SensorController;
use App\Http\Controllers\ZoneController;
use App\Http\Controllers\ZoneRuleController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\SimulateController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\WeatherController;

// Apply CORS middleware to all API routes
Route::middleware([Cors::class])->group(function () {

    // Zone Management
    Route::get('/zones', [ZoneController::class, 'index']);
    Route::get('/zones/{id}', [ZoneController::class, 'show']);
    Route::post('/zones', [ZoneController::class, 'store']);
    Route::put('/zones/{id}', [ZoneController::class, 'update']);
    Route::delete('/zones/{id}', [ZoneController::class, 'destroy']);

    // Zone Rules (Manual Mode Configuration)
    Route::get('/zones/{id}/rules', [ZoneRuleController::class, 'show']);
    Route::put('/zones/{id}/rules', [ZoneRuleController::class, 'update']);
    Route::post('/zones/{id}/rules/reset', [ZoneRuleController::class, 'reset']);

    // Sensor Management
    Route::get('/sensors', [SensorController::class, 'index']);
    Route::get('/sensors/{id}', [SensorController::class, 'show']);
    Route::post('/sensors', [SensorController::class, 'store']);
    Route::put('/sensors/{id}', [SensorController::class, 'update']);
    Route::delete('/sensors/{id}', [SensorController::class, 'destroy']);

    // Telemetry
    Route::get('/telemetry/latest', [TelemetryController::class, 'latest']);
    Route::get('/telemetry/history', [TelemetryController::class, 'history']);
    Route::get('/telemetry/statistics', [TelemetryController::class, 'statistics']);

    // Control & Simulation
    Route::post('/pump/{id}/{action}', [SensorController::class, 'controlPump']);
    Route::post('/simulate', [SimulateController::class, 'simulate']);

    // Alerts
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::get('/alerts/{id}', [AlertController::class, 'show']);
    Route::put('/alerts/{id}/handle', [AlertController::class, 'handle']);

    // Test endpoints (for testing error cases)
    Route::post('/test/pump-max-runtime', [TestController::class, 'testPumpMaxRuntime']);
    Route::post('/test/valve-max-runtime', [TestController::class, 'testValveMaxRuntime']);
    Route::post('/test/action-execution-failed', [TestController::class, 'testActionExecutionFailed']);
    Route::post('/test/pressure-leak', [TestController::class, 'testPressureLeak']);
    Route::post('/test/pressure-blockage', [TestController::class, 'testPressureBlockage']);
    Route::post('/test/low-battery', [TestController::class, 'testLowBattery']);
    Route::post('/test/stuck-sensor', [TestController::class, 'testStuckSensor']);
    Route::post('/test/pump-overload', [TestController::class, 'testPumpOverload']);
    Route::post('/test/leak-detection', [TestController::class, 'testLeakDetection']);

    // Weather
    Route::get('/weather/current', [WeatherController::class, 'current']);
    Route::get('/weather/forecast', [WeatherController::class, 'forecast']);
    Route::post('/weather/check', [WeatherController::class, 'check']);
    Route::get('/weather/history', [WeatherController::class, 'history']);
});
