<?php

namespace App\Console\Commands;

use App\Models\Sensor;
use App\Models\Telemetry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SensorSimulator extends Command
{
    protected $signature = 'simulate:hardware {--interval=3 : Interval in seconds between updates}';
    protected $description = 'Generate realistic telemetry data for all sensors';

    // State tracking for realistic simulation
    private array $sensorStates = [];

    public function handle()
    {
        $interval = (int) $this->option('interval');
        $this->info("Hardware Simulator started (interval: {$interval}s)... Press CTRL+C to stop.");

        while (true) {
            $sensors = Sensor::with('zone')->get();

            if ($sensors->isEmpty()) {
                $this->warn('No sensors found. Please seed the database first.');
                sleep($interval);
                continue;
            }

            foreach ($sensors as $sensor) {
                try {
                    $this->generateTelemetryForSensor($sensor);
                } catch (\Exception $e) {
                    $this->error("Error generating telemetry for sensor {$sensor->id}: " . $e->getMessage());
                    Log::error("Simulator error for sensor {$sensor->id}: " . $e->getMessage());
                }
            }

            // Trigger rule engine evaluation after each cycle (with error handling)
            try {
                app(\App\Services\RuleEngine::class)->evaluate();
            } catch (\Exception $e) {
                $this->error("Error in rule engine: " . $e->getMessage());
                Log::error("Rule engine error: " . $e->getMessage());
            }

            $this->info("Cycle completed at " . now()->toDateTimeString());
            sleep($interval);
        }
    }

    /**
     * Generate telemetry data based on sensor type
     */
    private function generateTelemetryForSensor(Sensor $sensor): void
    {
        $sensorId = $sensor->id;

        // Initialize state if not exists
        if (!isset($this->sensorStates[$sensorId])) {
            $this->sensorStates[$sensorId] = $this->initializeSensorState($sensor);
        }

        $state = &$this->sensorStates[$sensorId];

        switch ($sensor->type) {
            case 'soil_sensor':
                $this->generateSoilMoistureTelemetry($sensor, $state);
                break;
            case 'pump':
                $this->generatePumpTelemetry($sensor, $state);
                break;
            case 'flow':
                $this->generateFlowTelemetry($sensor, $state);
                break;
            case 'valve':
                $this->generateValveTelemetry($sensor, $state);
                break;
        }
    }

    /**
     * Initialize sensor state for realistic simulation
     */
    private function initializeSensorState(Sensor $sensor): array
    {
        return [
            'moisture' => rand(40, 60), // Start with moderate moisture
            'pump_status' => 0, // OFF
            'current' => 0,
            'flow' => 0,
            'valve_status' => 0,
            'trend' => 'stable', // stable, increasing, decreasing
        ];
    }

    /**
     * Generate soil moisture telemetry
     */
    private function generateSoilMoistureTelemetry(Sensor $sensor, array &$state): void
    {
        // Get current pump status from zone
        $pumpSensor = Sensor::where('zone_id', $sensor->zone_id)
            ->where('type', 'pump')
            ->first();

        $pumpStatus = 0;
        if ($pumpSensor) {
            $latestPumpStatus = $pumpSensor->getLatestTelemetry('pump_status');
            $pumpStatus = $latestPumpStatus ? (int) $latestPumpStatus->value : 0;
        }

        // Moisture changes based on pump status
        if ($pumpStatus === 1) {
            // Pump is ON - moisture increases gradually
            $state['moisture'] = min(100, $state['moisture'] + rand(1, 3));
            $state['trend'] = 'increasing';
        } else {
            // Pump is OFF - moisture decreases gradually (evaporation)
            $state['moisture'] = max(0, $state['moisture'] - rand(0, 2));
            $state['trend'] = 'decreasing';
        }

        // Add some natural variation
        $state['moisture'] += rand(-2, 2);
        $state['moisture'] = max(0, min(100, $state['moisture']));

        Telemetry::create([
            'sensor_id' => $sensor->id,
            'metric' => 'moisture',
            'value' => round($state['moisture'], 2),
            'recorded_at' => now(),
        ]);

        $this->line("  Sensor {$sensor->id} ({$sensor->type}): Moisture = " . round($state['moisture'], 2) . "%");
    }

    /**
     * Generate pump telemetry (current, status)
     */
    private function generatePumpTelemetry(Sensor $sensor, array &$state): void
    {
        // Get latest pump status from telemetry
        $latestStatus = $sensor->getLatestTelemetry('pump_status');
        $currentStatus = $latestStatus ? (int) $latestStatus->value : 0;

        // If no status exists, initialize to OFF
        if (!$latestStatus) {
            $currentStatus = 0;
            // Create initial pump_status telemetry
            Telemetry::create([
                'sensor_id' => $sensor->id,
                'metric' => 'pump_status',
                'value' => 0, // OFF
                'recorded_at' => now(),
            ]);
        }

        $state['pump_status'] = $currentStatus;

        if ($currentStatus === 1) {
            // Pump is ON - generate current reading
            // Normal range: 5-12A, with occasional spikes
            $baseCurrent = rand(5, 12);
            $variation = rand(0, 100) / 100; // 0-1A variation
            $state['current'] = $baseCurrent + $variation;

            // 5% chance of overload (for testing)
            if (rand(1, 100) <= 5) {
                $state['current'] = rand(16, 20); // Overload range
                $this->warn("  ⚠️  Sensor {$sensor->id} (pump): OVERLOAD detected! Current = " . round($state['current'], 2) . "A");
            }
        } else {
            // Pump is OFF
            $state['current'] = 0;
        }

        Telemetry::create([
            'sensor_id' => $sensor->id,
            'metric' => 'current',
            'value' => round($state['current'], 2),
            'recorded_at' => now(),
        ]);

        $statusText = $currentStatus === 1 ? 'ON' : 'OFF';
        $this->line("  Sensor {$sensor->id} (pump): Status = {$statusText}, Current = " . round($state['current'], 2) . "A");
    }

    /**
     * Generate flow rate telemetry
     */
    private function generateFlowTelemetry(Sensor $sensor, array &$state): void
    {
        // Check if pump is ON in the same zone
        $pumpSensor = Sensor::where('zone_id', $sensor->zone_id)
            ->where('type', 'pump')
            ->first();

        $pumpStatus = 0;
        if ($pumpSensor) {
            $latestPumpStatus = $pumpSensor->getLatestTelemetry('pump_status');
            $pumpStatus = $latestPumpStatus ? (int) $latestPumpStatus->value : 0;
        }

        if ($pumpStatus === 1) {
            // Pump is ON - generate flow reading
            $expectedFlow = $sensor->meta['expected_flow'] ?? 10.0;
            $state['flow'] = $expectedFlow + (rand(-200, 200) / 100); // ±2 L/min variation

            // 3% chance of leak (for testing)
            if (rand(1, 100) <= 3) {
                $state['flow'] = $expectedFlow * 2; // Double the flow = leak
                $this->warn("  ⚠️  Sensor {$sensor->id} (flow): LEAK detected! Flow = " . round($state['flow'], 2) . " L/min");
            }
        } else {
            // Pump is OFF - no flow
            $state['flow'] = 0;
        }

        Telemetry::create([
            'sensor_id' => $sensor->id,
            'metric' => 'flow',
            'value' => round($state['flow'], 2),
            'recorded_at' => now(),
        ]);

        $this->line("  Sensor {$sensor->id} (flow): Flow = " . round($state['flow'], 2) . " L/min");
            }

    /**
     * Generate valve telemetry
     */
    private function generateValveTelemetry(Sensor $sensor, array &$state): void
    {
        // Get latest valve status from telemetry
        $latestStatus = $sensor->getLatestTelemetry('valve_status');
        $currentStatus = $latestStatus ? (int) $latestStatus->value : 0;

        // If no status exists, initialize to CLOSED
        if (!$latestStatus) {
            $currentStatus = 0;
            // Create initial valve_status telemetry
            Telemetry::create([
                'sensor_id' => $sensor->id,
                'metric' => 'valve_status',
                'value' => 0, // CLOSED
                'recorded_at' => now(),
            ]);
        }

        $state['valve_status'] = $currentStatus;

        // Valve status doesn't change unless action is taken
        // Just log current status
        $statusText = $currentStatus === 1 ? 'OPEN' : 'CLOSED';
        $this->line("  Sensor {$sensor->id} (valve): Status = {$statusText}");
    }
}
