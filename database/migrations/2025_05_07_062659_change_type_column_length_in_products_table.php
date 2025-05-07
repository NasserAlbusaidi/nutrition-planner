<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Import DB for raw alter statement if using ENUM

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // If it's currently a VARCHAR and you want to change its length
            $table->string('type', 50)->nullable()->change(); // Increase length to 50

            // If it's currently an ENUM and you need to redefine it with new/longer values:
            // IMPORTANT: Modifying ENUMs can be database-specific and sometimes tricky.
            // The ->change() method might not work directly for ENUM redefinition on all DBs.
            // For MySQL, you might need a raw statement or to use Doctrine DBAL.
            // Example of a possible approach (TEST THIS CAREFULLY, especially if you have data):
            // $productTypes = [
            //     Product::TYPE_DRINK_MIX, Product::TYPE_GEL, Product::TYPE_ENERGY_BAR,
            //     Product::TYPE_ENERGY_CHEW, Product::TYPE_REAL_FOOD, Product::TYPE_HYDRATION_TABLET,
            //     Product::TYPE_RECOVERY_DRINK, Product::TYPE_PLAIN_WATER
            // ];
            // $typeList = "'" . implode("','", $productTypes) . "'";
            // DB::statement("ALTER TABLE products MODIFY COLUMN type ENUM({$typeList}) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL");
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Revert to a previous state if possible, e.g.
            // $table->string('type', PREVIOUS_LENGTH)->nullable()->change();
            // Reverting ENUMs is also complex, you'd redefine with the old list.
        });
    }
};
