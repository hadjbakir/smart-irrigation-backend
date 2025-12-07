<?php

namespace App\Console\Commands;

use App\Models\Action;
use App\Models\Sensor;
use App\Models\Telemetry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessActions extends Command
{
    protected $signature = 'actions:process';
    protected $description = 'Process pending actions from the actions table';

    public function handle()
    {
        $this->info('Action Worker: Starting to process pending actions...');

        while (true) {
            $pendingActions = Action::pending()
                ->orderBy('created_at', 'asc')
                ->limit(10)
                ->get();

            if ($pendingActions->isEmpty()) {
                sleep(2); // Wait 2 seconds if no actions
                continue;
            }

            foreach ($pendingActions as $action) {
                $this->processAction($action);
            }

            sleep(1); // Small delay between batches
        }
    }

    /**
     * Process a single action with execution tracking and verification
     */
    private function processAction(Action $action): void
    {
        $this->info("Processing action {$action->id}: {$action->action} for sensor {$action->sensor_id}");

        try {
            $sensor = $action->sensor;

            if (!$sensor) {
                $this->markActionFailed($action, 'Sensor not found');
                return;
            }

            // Set requested_at if not set
            if (!$action->requested_at) {
                $action->update(['requested_at' => now()]);
            }

            $executionStart = now();

            // Execute action based on type
            switch ($action->action) {
                case 'pump_on':
                    $this->executePumpOn($action, $sensor);
                    break;
                case 'pump_off':
                    $this->executePumpOff($action, $sensor);
                    break;
                case 'open_valve':
                    $this->executeOpenValve($action, $sensor);
                    break;
                case 'close_valve':
                    $this->executeCloseValve($action, $sensor);
                    break;
                default:
                    $this->markActionFailed($action, "Unknown action type: {$action->action}");
                    return;
            }

            // Verify action execution
            $verified = $this->verifyActionExecution($action, $sensor);

            if ($verified) {
                $executionTime = $executionStart->diffInMilliseconds(now());
                $this->markActionDone($action, $executionStart, $executionTime);
                Log::info("Action {$action->id} executed successfully: {$action->action} (execution time: {$executionTime}ms)");
            } else {
                // Action didn't execute properly, retry or fail
                $this->handleActionVerificationFailure($action, $sensor);
            }

        } catch (\Exception $e) {
            $this->markActionFailed($action, $e->getMessage());
            Log::error("Action {$action->id} failed: " . $e->getMessage());
        }
    }

    /**
     * Execute pump_on action
     */
    private function executePumpOn(Action $action, Sensor $sensor): void
    {
        // Simulate hardware call - in production, this would call actual hardware API
        $this->info("  → Simulating: Turning ON pump for sensor {$sensor->id}");

        // Update sensor state
        Telemetry::create([
            'sensor_id' => $sensor->id,
            'metric' => 'pump_status',
            'value' => 1, // 1 = ON
            'recorded_at' => now(),
        ]);

        // Update sensor pump_started_at timestamp
        $sensor->update(['pump_started_at' => now()]);

        // Generate initial pump current telemetry
        Telemetry::create([
            'sensor_id' => $sensor->id,
            'metric' => 'current',
            'value' => rand(5, 10) + (rand(0, 100) / 100), // 5-10A with decimals
            'recorded_at' => now(),
        ]);
    }

    /**
     * Execute pump_off action
     */
    private function executePumpOff(Action $action, Sensor $sensor): void
    {
        $this->info("  → Simulating: Turning OFF pump for sensor {$sensor->id}");

        Telemetry::create([
            'sensor_id' => $sensor->id,
            'metric' => 'pump_status',
            'value' => 0, // 0 = OFF
            'recorded_at' => now(),
        ]);

        // Clear pump_started_at timestamp
        $sensor->update(['pump_started_at' => null]);

        // Set current to 0 when pump is OFF
        Telemetry::create([
            'sensor_id' => $sensor->id,
            'metric' => 'current',
            'value' => 0,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Execute open_valve action
     */
    private function executeOpenValve(Action $action, Sensor $sensor): void
    {
        $this->info("  → Simulating: Opening valve for sensor {$sensor->id}");

        Telemetry::create([
            'sensor_id' => $sensor->id,
            'metric' => 'valve_status',
            'value' => 1, // 1 = OPEN
            'recorded_at' => now(),
        ]);

        // Update sensor valve_opened_at timestamp
        $sensor->update(['valve_opened_at' => now()]);
    }

    /**
     * Execute close_valve action
     */
    private function executeCloseValve(Action $action, Sensor $sensor): void
    {
        $this->info("  → Simulating: Closing valve for sensor {$sensor->id}");

        Telemetry::create([
            'sensor_id' => $sensor->id,
            'metric' => 'valve_status',
            'value' => 0, // 0 = CLOSED
            'recorded_at' => now(),
        ]);

        // Clear valve_opened_at timestamp
        $sensor->update(['valve_opened_at' => null]);
    }

    /**
     * Verify that action was actually executed by checking telemetry
     */
    private function verifyActionExecution(Action $action, Sensor $sensor): bool
    {
        // Wait a moment for telemetry to be written
        sleep(1);

        switch ($action->action) {
            case 'pump_on':
                $latestStatus = $sensor->getLatestTelemetry('pump_status');
                return $latestStatus && $latestStatus->value == 1;

            case 'pump_off':
                $latestStatus = $sensor->getLatestTelemetry('pump_status');
                return $latestStatus && $latestStatus->value == 0;

            case 'open_valve':
                $latestStatus = $sensor->getLatestTelemetry('valve_status');
                return $latestStatus && $latestStatus->value == 1;

            case 'close_valve':
                $latestStatus = $sensor->getLatestTelemetry('valve_status');
                return $latestStatus && $latestStatus->value == 0;

            default:
                return true; // Unknown action type, assume success
        }
    }

    /**
     * Handle action verification failure - retry or create alert
     */
    private function handleActionVerificationFailure(Action $action, Sensor $sensor): void
    {
        $retryCount = ($action->retry_count ?? 0) + 1;
        $maxRetries = $action->max_retries ?? 3;

        if ($retryCount < $maxRetries) {
            // Retry the action
            $action->update([
                'retry_count' => $retryCount,
                'status' => 'pending', // Reset to pending for retry
            ]);

            Log::warning("Action {$action->id} verification failed, retrying ({$retryCount}/{$maxRetries})");
        } else {
            // Max retries reached, mark as failed and create alert
            $this->markActionFailed($action, "Action verification failed after {$maxRetries} attempts");
            
            \App\Models\Alert::create([
                'zone_id' => $sensor->zone_id,
                'sensor_id' => $sensor->id,
                'level' => 'critical',
                'type' => 'action_execution_failed',
                'message' => "Failed to execute {$action->action} for sensor '{$sensor->name}' after {$maxRetries} attempts. Please check hardware.",
                'handled' => false,
            ]);

            Log::error("Action {$action->id} failed after {$maxRetries} retry attempts");
        }
    }

    /**
     * Mark action as done with execution tracking
     */
    private function markActionDone(Action $action, $executedAt, int $executionTimeMs): void
    {
        $action->update([
            'status' => 'done',
            'executed_at' => $executedAt,
            'execution_time_ms' => $executionTimeMs,
        ]);
    }

    /**
     * Mark action as failed
     */
    private function markActionFailed(Action $action, string $reason): void
    {
        $action->update([
            'status' => 'failed',
            'payload' => array_merge($action->payload ?? [], ['error' => $reason]),
        ]);
    }
}







