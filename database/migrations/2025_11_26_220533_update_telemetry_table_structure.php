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
        // Check if old columns exist
        $hasOldStructure = Schema::hasColumn('telemetry', 'moisture') || 
                          Schema::hasColumn('telemetry', 'pump_status');

        if ($hasOldStructure) {
            // Add new columns
            Schema::table('telemetry', function (Blueprint $table) {
                if (!Schema::hasColumn('telemetry', 'metric')) {
                    $table->string('metric')->after('sensor_id');
                }
                if (!Schema::hasColumn('telemetry', 'value')) {
                    $table->double('value')->after('metric');
                }
                if (!Schema::hasColumn('telemetry', 'recorded_at')) {
                    $table->timestamp('recorded_at')->useCurrent()->after('value');
                }
            });

            // Drop old columns
            Schema::table('telemetry', function (Blueprint $table) {
                if (Schema::hasColumn('telemetry', 'moisture')) {
                    $table->dropColumn('moisture');
                }
                if (Schema::hasColumn('telemetry', 'pump_status')) {
                    $table->dropColumn('pump_status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back old columns (for rollback)
        Schema::table('telemetry', function (Blueprint $table) {
            if (!Schema::hasColumn('telemetry', 'moisture')) {
                $table->double('moisture')->after('sensor_id');
            }
            if (!Schema::hasColumn('telemetry', 'pump_status')) {
                $table->string('pump_status')->after('moisture');
            }
        });

        // Drop new columns
        Schema::table('telemetry', function (Blueprint $table) {
            if (Schema::hasColumn('telemetry', 'metric')) {
                $table->dropColumn('metric');
            }
            if (Schema::hasColumn('telemetry', 'value')) {
                $table->dropColumn('value');
            }
            if (Schema::hasColumn('telemetry', 'recorded_at')) {
                $table->dropColumn('recorded_at');
            }
        });
    }
};
