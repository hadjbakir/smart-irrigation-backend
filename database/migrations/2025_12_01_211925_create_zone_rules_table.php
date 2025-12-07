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
        Schema::create('zone_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->onDelete('cascade');
            
            // Rule thresholds
            $table->double('moisture_threshold')->default(30.0)->comment('Start irrigation below this %');
            $table->double('moisture_target')->default(60.0)->comment('Stop irrigation when reaching this %');
            $table->double('pump_overload_current')->default(15.0)->comment('Emergency shutdown above this (Amperes)');
            $table->double('flow_leak_multiplier')->default(1.5)->comment('Leak detection: flow > expected * multiplier');
            
            // Rule toggles (enable/disable specific rules)
            $table->boolean('enable_low_moisture')->default(true);
            $table->boolean('enable_pump_overload')->default(true);
            $table->boolean('enable_leak_detection')->default(true);
            $table->boolean('enable_rain_forecast')->default(true);
            
            // Additional settings
            $table->integer('irrigation_duration_minutes')->nullable()->comment('Max irrigation duration (null = unlimited)');
            $table->json('schedule')->nullable()->comment('Time-based schedule rules');
            
            $table->timestamps();
            
            // One rule set per zone
            $table->unique('zone_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zone_rules');
    }
};
