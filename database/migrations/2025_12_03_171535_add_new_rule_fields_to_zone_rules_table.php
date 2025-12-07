<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('zone_rules', function (Blueprint $table) {
            // Pump/Valve runtime protection
            $table->integer('max_pump_duration_minutes')->default(60)->after('irrigation_duration_minutes')->comment('Max pump runtime before auto-shutoff');
            $table->integer('max_valve_duration_minutes')->default(120)->after('max_pump_duration_minutes')->comment('Max valve open time before auto-close');
            
            // Pressure monitoring
            $table->double('pressure_leak_threshold_multiplier')->default(1.3)->after('flow_leak_multiplier')->comment('Pressure increase multiplier for leak detection');
            $table->double('pressure_blockage_threshold_multiplier')->default(0.7)->after('pressure_leak_threshold_multiplier')->comment('Pressure decrease multiplier for blockage detection');
            $table->boolean('enable_pressure_monitoring')->default(true)->after('enable_rain_forecast')->comment('Enable pressure-based leak/blockage detection');
            
            // Battery monitoring
            $table->double('battery_low_threshold')->default(20.0)->after('enable_pressure_monitoring')->comment('Battery level threshold for low battery alert');
            $table->boolean('enable_battery_monitoring')->default(true)->after('battery_low_threshold')->comment('Enable battery level monitoring');
            
            // Stuck sensor detection
            $table->integer('stuck_sensor_timeout_minutes')->default(30)->after('enable_battery_monitoring')->comment('Time before sensor is considered stuck');
            $table->boolean('enable_stuck_sensor_detection')->default(true)->after('stuck_sensor_timeout_minutes')->comment('Enable stuck sensor detection');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zone_rules', function (Blueprint $table) {
            //
        });
    }
};
