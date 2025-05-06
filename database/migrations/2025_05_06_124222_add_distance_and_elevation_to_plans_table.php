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
            $table->decimal('estimated_distance_km', 8, 2)->nullable()->after('estimated_duration_seconds'); // Or adjust position
            $table->integer('estimated_elevation_m')->nullable()->after('estimated_distance_km');     // Or adjust position
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['estimated_distance_km', 'estimated_elevation_m']);

        });
    }
};
