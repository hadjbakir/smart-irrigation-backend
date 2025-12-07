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
        Schema::table('sensors', function (Blueprint $table) {
            $table->double('battery_level')->nullable()->after('meta')->comment('Battery level percentage (0-100)');
            $table->timestamp('last_battery_update')->nullable()->after('battery_level')->comment('Last time battery level was updated');
            $table->timestamp('pump_started_at')->nullable()->after('last_battery_update')->comment('When pump was last turned on');
            $table->timestamp('valve_opened_at')->nullable()->after('pump_started_at')->comment('When valve was last opened');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sensors', function (Blueprint $table) {
            //
        });
    }
};
