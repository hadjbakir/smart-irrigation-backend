<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\DB;


class SimulateRun extends Command
{
    protected $signature = 'simulate:run';
    protected $description = 'Simulate soil moisture and pump status';

    public function handle()
    {
        $this->info('Simulation started... CTRL+C to stop.');

        while (true) {
            $payload = [
                'sensor_id' => 1,
                'moisture' => rand(20, 90),
                'pump_status' => rand(0,1) ? 'ON' : 'OFF',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // store in DB
            DB::table('telemetry')->insert($payload);

            $this->info('Saved: ' . json_encode($payload));

            sleep(3); // repeat every 3 seconds
        }
    }
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    /**
     * The console command description.
     *
     * @var string
     */

    /**
     * Execute the console command.
     */

}
