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
            $table->float('recommended_total_carbs_g')->nullable()->after('estimated_total_sodium_mg');
            $table->integer('recommended_total_fluid_ml')->nullable()->after('recommended_total_carbs_g');
            $table->integer('recommended_total_sodium_mg')->nullable()->after('recommended_total_fluid_ml');
        });
    }

    public function down()
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'recommended_total_carbs_g',
                'recommended_total_fluid_ml',
                'recommended_total_sodium_mg',
            ]);
        });
    }
};
