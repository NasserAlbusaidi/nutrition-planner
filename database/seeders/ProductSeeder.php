<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB; // Use DB facade

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing default products first to avoid duplicates if run multiple times
        DB::table('products')->whereNull('user_id')->delete();

        DB::table('products')->insert([
            // Real Foods
            [
                'user_id' => null, // Default product
                'name' => 'Banana (Medium)',
                'type' => 'real_food',
                'carbs_g' => 27,
                'sodium_mg' => 1,
                'caffeine_mg' => 0,
                'serving_size_description' => '1 medium banana',
                'serving_volume_ml' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => null,
                'name' => 'Dates (Medjool)',
                'type' => 'real_food',
                'carbs_g' => 18, // Per date
                'sodium_mg' => 0,
                'caffeine_mg' => 0,
                'serving_size_description' => '1 date',
                'serving_volume_ml' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Example Gels (SIS)
            [
                'user_id' => null,
                'name' => 'SIS Go Isotonic Gel (Orange)',
                'type' => 'gel',
                'carbs_g' => 22,
                'sodium_mg' => 10, // Check actual values
                'caffeine_mg' => 0,
                'serving_size_description' => '1 gel (60ml)',
                'serving_volume_ml' => 60,
                'created_at' => now(),
                'updated_at' => now(),
            ],
             [
                'user_id' => null,
                'name' => 'SIS Go Energy + Caffeine Gel (Berry)',
                'type' => 'gel',
                'carbs_g' => 22,
                'sodium_mg' => 15, // Check actual values
                'caffeine_mg' => 75, // Check actual values
                'serving_size_description' => '1 gel (60ml)',
                'serving_volume_ml' => 60,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Example Drink Mix
             [
                'user_id' => null,
                'name' => 'SIS Go Electrolyte (Lemon & Lime)',
                'type' => 'drink_mix',
                'carbs_g' => 36, // Per serving (e.g., 40g powder)
                'sodium_mg' => 240, // Per serving (check actual values)
                'caffeine_mg' => 0,
                'serving_size_description' => '1 serving (40g powder)', // User mixes with water
                'serving_volume_ml' => null, // Volume depends on user mixing
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Add more default products as needed...


        ]);




    }
}
