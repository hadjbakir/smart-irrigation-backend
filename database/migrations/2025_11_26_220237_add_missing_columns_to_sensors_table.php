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
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('sensors', 'type')) {
                $table->string('type')->after('zone_id'); // soil_sensor, flow, pump, valve
            }
            if (!Schema::hasColumn('sensors', 'name')) {
                $table->string('name')->nullable()->after('type');
            }
            if (!Schema::hasColumn('sensors', 'meta')) {
                $table->json('meta')->nullable()->after('name');
            }
        });
        
        // Try to add foreign key constraint if it doesn't exist
        // This is done separately to avoid issues if constraint already exists
        try {
            Schema::table('sensors', function (Blueprint $table) {
                $table->foreign('zone_id')->references('id')->on('zones')->onDelete('cascade');
            });
        } catch (\Exception $e) {
            // Foreign key might already exist, ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sensors', function (Blueprint $table) {
            // Drop foreign key if it exists
            try {
                $table->dropForeign(['zone_id']);
            } catch (\Exception $e) {
                // Foreign key might not exist, ignore
            }
            
            // Drop columns
            if (Schema::hasColumn('sensors', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('sensors', 'name')) {
                $table->dropColumn('name');
            }
            if (Schema::hasColumn('sensors', 'meta')) {
                $table->dropColumn('meta');
            }
        });
    }
};
