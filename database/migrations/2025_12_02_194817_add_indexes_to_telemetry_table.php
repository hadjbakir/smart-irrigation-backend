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
        Schema::table('telemetry', function (Blueprint $table) {
            // Composite index for fast latest telemetry queries (sensor_id + metric + recorded_at)
            // This speeds up queries that filter by sensor and metric and order by recorded_at
            if (!$this->hasIndex('telemetry', 'idx_sensor_metric_recorded')) {
                $table->index(['sensor_id', 'metric', 'recorded_at'], 'idx_sensor_metric_recorded');
            }
            // Index for sensor_id lookups (if not already exists from foreign key)
            if (!$this->hasIndex('telemetry', 'telemetry_sensor_id_index')) {
                $table->index('sensor_id', 'telemetry_sensor_id_index');
            }
        });
    }

    private function hasIndex($table, $indexName): bool
    {
        try {
            $connection = Schema::getConnection();
            $databaseName = $connection->getDatabaseName();
            
            $result = $connection->select(
                "SELECT COUNT(*) as count FROM information_schema.statistics 
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                [$databaseName, $table, $indexName]
            );
            
            return isset($result[0]) && $result[0]->count > 0;
        } catch (\Exception $e) {
            // If query fails, assume index doesn't exist
            return false;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telemetry', function (Blueprint $table) {
            //
        });
    }
};
