<?php

namespace Database\Seeders;

use App\Models\Zone;
use App\Models\Sensor;
use Illuminate\Database\Seeder;

class SensorSeeder extends Seeder
{
    public function run(): void
    {
        // Create zones if they don't exist
        $zone1 = Zone::firstOrCreate(
            ['name' => 'Zone A'],
            ['notes' => 'Main irrigation zone']
        );

        $zone2 = Zone::firstOrCreate(
            ['name' => 'Zone B'],
            ['notes' => 'Secondary irrigation zone']
        );

        $zone3 = Zone::firstOrCreate(
            ['name' => 'Zone C'],
            ['notes' => 'Tertiary irrigation zone']
        );

        // Create sensors for Zone A
        Sensor::firstOrCreate(
            ['zone_id' => $zone1->id, 'type' => 'soil_sensor'],
            ['name' => 'Soil Sensor A1', 'meta' => null]
        );

        Sensor::firstOrCreate(
            ['zone_id' => $zone1->id, 'type' => 'pump'],
            ['name' => 'Pump A1', 'meta' => null]
        );

        Sensor::firstOrCreate(
            ['zone_id' => $zone1->id, 'type' => 'flow'],
            ['name' => 'Flow Sensor A1', 'meta' => ['expected_flow' => 10.0]]
        );

        // Create sensors for Zone B
        Sensor::firstOrCreate(
            ['zone_id' => $zone2->id, 'type' => 'soil_sensor'],
            ['name' => 'Soil Sensor B1', 'meta' => null]
        );

        Sensor::firstOrCreate(
            ['zone_id' => $zone2->id, 'type' => 'pump'],
            ['name' => 'Pump B1', 'meta' => null]
        );

        Sensor::firstOrCreate(
            ['zone_id' => $zone2->id, 'type' => 'flow'],
            ['name' => 'Flow Sensor B1', 'meta' => ['expected_flow' => 12.0]]
        );

        // Create sensors for Zone C
        Sensor::firstOrCreate(
            ['zone_id' => $zone3->id, 'type' => 'soil_sensor'],
            ['name' => 'Soil Sensor C1', 'meta' => null]
        );

        Sensor::firstOrCreate(
            ['zone_id' => $zone3->id, 'type' => 'pump'],
            ['name' => 'Pump C1', 'meta' => null]
        );

        Sensor::firstOrCreate(
            ['zone_id' => $zone3->id, 'type' => 'flow'],
            ['name' => 'Flow Sensor C1', 'meta' => ['expected_flow' => 8.0]]
        );

        // Add a valve sensor to Zone A
        Sensor::firstOrCreate(
            ['zone_id' => $zone1->id, 'type' => 'valve'],
            ['name' => 'Valve A1', 'meta' => null]
        );

        $this->command->info('Created ' . Zone::count() . ' zones and ' . Sensor::count() . ' sensors');
    }
}
