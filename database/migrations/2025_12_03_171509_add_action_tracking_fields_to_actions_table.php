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
        Schema::table('actions', function (Blueprint $table) {
            $table->timestamp('requested_at')->nullable()->after('created_at')->comment('When action was requested');
            $table->timestamp('executed_at')->nullable()->after('requested_at')->comment('When action was actually executed');
            $table->integer('retry_count')->default(0)->after('status')->comment('Number of retry attempts');
            $table->integer('max_retries')->default(3)->after('retry_count')->comment('Maximum retry attempts');
            $table->integer('execution_time_ms')->nullable()->after('executed_at')->comment('Time between request and execution in milliseconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('actions', function (Blueprint $table) {
            //
        });
    }
};
