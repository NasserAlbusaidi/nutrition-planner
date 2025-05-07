<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('plans', function (Blueprint $table) {
            $table->json('hourly_targets_data')->nullable()->after('weather_summary');
        });
    }
    public function down(): void {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('hourly_targets_data');
        });
    }
};
