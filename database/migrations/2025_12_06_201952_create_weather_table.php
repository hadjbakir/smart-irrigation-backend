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
        Schema::create('weather', function (Blueprint $table) {
            $table->id();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('temperature', 5, 2)->nullable();
            $table->integer('humidity')->nullable();
            $table->decimal('pressure', 7, 2)->nullable();
            $table->decimal('wind_speed', 5, 2)->nullable();
            $table->string('condition')->nullable(); // Rain, Clear, Clouds, etc.
            $table->string('description')->nullable();
            $table->boolean('is_raining')->default(false);
            $table->decimal('rain_amount', 5, 2)->default(0)->comment('Rain amount in mm');
            $table->json('data')->nullable()->comment('Full weather API response');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['latitude', 'longitude', 'recorded_at']);
            $table->index('is_raining');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather');
    }
};
