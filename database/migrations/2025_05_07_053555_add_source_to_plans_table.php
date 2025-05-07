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
        Schema::table('plans', function (Blueprint $table) {
            $table->string('source')->default('strava')->after('weather_summary'); // Or enum
            // If you DID decide to add distance/elevation to plans table:
            // $table->decimal('estimated_distance_km', 8, 2)->nullable()->after('estimated_duration_seconds');
            // $table->integer('estimated_elevation_m')->nullable()->after('estimated_distance_km');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            //
        });
    }
};
