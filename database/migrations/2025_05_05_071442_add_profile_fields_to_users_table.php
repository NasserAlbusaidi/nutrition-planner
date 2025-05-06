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
        Schema::table('users', function (Blueprint $table) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('weight_kg', 5, 2)->nullable()->after('password');
                $table->integer('ftp_watts')->nullable()->after('weight_kg');
                $table->enum('sweat_level', ['light', 'average', 'heavy'])->nullable()->after('ftp_watts');
                $table->enum('salt_loss_level', ['low', 'average', 'high'])->nullable()->after('sweat_level');
                $table->string('strava_user_id')->unique()->nullable()->after('salt_loss_level');
                $table->text('strava_access_token')->nullable()->after('strava_user_id'); // Consider encryption
                $table->text('strava_refresh_token')->nullable()->after('strava_access_token'); // Consider encryption
                $table->timestamp('strava_token_expires_at')->nullable()->after('strava_refresh_token');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'weight_kg',
                'ftp_watts',
                'sweat_level',
                'salt_loss_level',
                'strava_user_id',
                'strava_access_token',
                'strava_refresh_token',
                'strava_token_expires_at',
            ]);
        });
    }
};
