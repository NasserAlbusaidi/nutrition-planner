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
        Schema::create('plans', function (Blueprint $table) {
            $table->id(); // Or $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('strava_route_id')->nullable(); // Strava IDs can be large numbers or strings
            $table->string('strava_route_name')->nullable();
            $table->timestamp('planned_start_time');
            $table->enum('planned_intensity', ['easy', 'endurance', 'tempo', 'threshold', 'race_pace', 'steady_group_ride']); // Add more if needed
            $table->integer('estimated_duration_seconds');
            $table->integer('estimated_avg_power_watts')->nullable();
            $table->decimal('estimated_total_carbs_g', 8, 2);
            $table->decimal('estimated_total_fluid_ml', 8, 2);
            $table->decimal('estimated_total_sodium_mg', 8, 2);
            $table->text('weather_summary')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
