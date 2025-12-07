<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$results = [];

try {
    $zones = \App\Models\Zone::count();
    $sensors = \App\Models\Sensor::count();
    $telemetry = \App\Models\Telemetry::count();
    
    $results[] = "SUCCESS: Zones=$zones, Sensors=$sensors, Telemetry=$telemetry";
    
    if ($sensors > 0) {
        $results[] = "\nSensors:";
        foreach (\App\Models\Sensor::with('zone')->get() as $s) {
            $results[] = "  ID:{$s->id} Type:{$s->type} Name:{$s->name} Zone:" . ($s->zone->name ?? 'N/A');
        }
    }
} catch (\Exception $e) {
    $results[] = "ERROR: " . $e->getMessage();
}

file_put_contents('db_check.txt', implode("\n", $results));
echo implode("\n", $results);









