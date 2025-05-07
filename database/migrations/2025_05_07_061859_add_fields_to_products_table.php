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
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('name');
            $table->decimal('protein_g', 8, 2)->default(0)->after('carbs_g');
            $table->decimal('fat_g', 8, 2)->default(0)->after('protein_g');
            $table->decimal('potassium_mg', 8, 2)->default(0)->after('sodium_mg');
            $table->decimal('magnesium_mg', 8, 2)->default(0)->after('potassium_mg');
            // Rename serving_volume_ml to serving_size_ml if that makes more sense for liquids
            // If you want to keep serving_volume_ml for powders and add serving_size_ml for ready-to-drink:
            $table->renameColumn('serving_volume_ml', 'powder_volume_for_mixing_ml'); // Example rename
            $table->decimal('serving_size_g', 8, 2)->nullable()->after('caffeine_mg'); // For solids/powders
            $table->decimal('serving_size_ml', 8, 2)->nullable()->after('serving_size_g'); // For liquids
            $table->string('serving_size_units', 50)->nullable()->after('serving_size_ml'); // e.g. '1 scoop', '1 tablet'
            // serving_size_description already exists
            $table->text('notes')->nullable()->after('serving_size_description');
            $table->boolean('is_active')->default(true)->after('notes');
            $table->boolean('is_global')->default(false)->after('is_active'); // Default to false unless it's a global item
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['brand', 'protein_g', 'fat_g', 'potassium_mg', 'magnesium_mg', 'serving_size_g', 'serving_size_units', 'notes', 'is_active', 'is_global']);
            // $table->renameColumn('powder_volume_for_mixing_ml', 'serving_volume_ml'); // Revert rename if done
        });
    }
};
