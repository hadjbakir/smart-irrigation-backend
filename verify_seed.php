<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$zones = \App\Models\Zone::count();
$sensors = \App\Models\Sensor::count();

echo "Zones: $zones\n";
echo "Sensors: $sensors\n\n";

if ($sensors > 0) {
    echo "Sensor Details:\n";
    \App\Models\Sensor::with('zone')->get()->each(function($s) {
        echo "  - Sensor {$s->id}: {$s->type} - {$s->name} (Zone: " . ($s->zone->name ?? 'N/A') . ")\n";
    });
} else {
    echo "No sensors found! Run: php artisan db:seed\n";
}









