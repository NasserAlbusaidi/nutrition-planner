<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product; // Import the Product model
use Illuminate\Support\Facades\DB; // If needed for raw queries or disabling foreign keys

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Optional: Truncate the table first to avoid duplicates on re-seed
        // Product::truncate(); // Be careful with this in production!

        $products = [
            // --- PLAIN WATER ---
            [
                'name' => 'Water (Plain)',
                'brand' => 'Generic',
                'type' => Product::TYPE_PLAIN_WATER,
                'carbs_g' => 0, 'protein_g' => 0, 'fat_g' => 0,
                'sodium_mg' => 0, 'potassium_mg' => 0, 'magnesium_mg' => 0, 'caffeine_mg' => 0,
                'serving_size_g' => null, 'serving_size_ml' => 250, // e.g., "1 sip" or a small serving size
                'serving_size_units' => 'ml',
                'serving_size_description' => 'Approx. 250ml / 1 large gulp',
                'notes' => 'Plain hydration.',
                'is_active' => true, 'is_global' => true, 'user_id' => null,
            ],

            // --- FRUITS (Real Food) ---
            [
                'name' => 'Banana (Medium)',
                'brand' => 'Generic Fruit',
                'type' => Product::TYPE_REAL_FOOD,
                'carbs_g' => 27, 'protein_g' => 1.3, 'fat_g' => 0.4,
                'sodium_mg' => 1, 'potassium_mg' => 422, 'magnesium_mg' => 32, 'caffeine_mg' => 0,
                'serving_size_g' => 118, 'serving_size_ml' => null,
                'serving_size_units' => '1 medium',
                'serving_size_description' => '1 medium banana (approx 118g)',
                'is_active' => true, 'is_global' => true, 'user_id' => null,
            ],
            [
                'name' => 'Orange (Medium)',
                'brand' => 'Generic Fruit',
                'type' => Product::TYPE_REAL_FOOD,
                'carbs_g' => 15, 'protein_g' => 1, 'fat_g' => 0.2,
                'sodium_mg' => 0, 'potassium_mg' => 237, 'magnesium_mg' => 13, 'caffeine_mg' => 0,
                'serving_size_g' => 130, 'serving_size_ml' => null, // Fruit is solid
                'serving_size_units' => '1 medium',
                'serving_size_description' => '1 medium orange (approx 130g)',
                'is_active' => true, 'is_global' => true, 'user_id' => null,
            ],
            [
                'name' => 'Dates (Medjool, 2)',
                'brand' => 'Generic Fruit',
                'type' => Product::TYPE_REAL_FOOD,
                'carbs_g' => 36, 'protein_g' => 0.8, 'fat_g' => 0.1,
                'sodium_mg' => 0, 'potassium_mg' => 334, 'magnesium_mg' => 26, 'caffeine_mg' => 0,
                'serving_size_g' => 48, 'serving_size_ml' => null,
                'serving_size_units' => '2 dates',
                'serving_size_description' => '2 medjool dates (approx 48g total)',
                'is_active' => true, 'is_global' => true, 'user_id' => null,
            ],

            // --- ENERGY GELS ---
            [
                'name' => 'GU Energy Gel (Generic Vanilla Bean)',
                'brand' => 'GU Energy',
                'type' => Product::TYPE_GEL,
                'carbs_g' => 22, 'protein_g' => 0, 'fat_g' => 0,
                'sodium_mg' => 60, 'potassium_mg' => 40, 'magnesium_mg' => 0,'caffeine_mg' => 20, // Some have caffeine
                'serving_size_g' => 32, 'serving_size_ml' => null, // Gels are often measured by weight
                'serving_size_units' => '1 packet',
                'serving_size_description' => '1 gel packet (32g)',
                'is_active' => true, 'is_global' => true, 'user_id' => null,
            ],
            [
                'name' => 'SIS Go Isotonic Gel (Orange)',
                'brand' => 'Science in Sport (SIS)',
                'type' => Product::TYPE_GEL,
                'carbs_g' => 22, 'protein_g' => 0, 'fat_g' => 0,
                'sodium_mg' => 10, 'potassium_mg' => 5, 'magnesium_mg' => 0,'caffeine_mg' => 0,
                'serving_size_g' => null, 'serving_size_ml' => 60, // Isotonic gels often by volume
                'serving_size_units' => '1 gel',
                'serving_size_description' => '1 isotonic gel (60ml)',
                'is_active' => true, 'is_global' => true, 'user_id' => null,
            ],
            [
                'name' => 'Maurten Gel 100',
                'brand' => 'Maurten',
                'type' => Product::TYPE_GEL,
                'carbs_g' => 25, 'protein_g' => 0, 'fat_g' => 0,
                'sodium_mg' => 35, 'potassium_mg' => 0, 'magnesium_mg' => 0, 'caffeine_mg' => 0,
                'serving_size_g' => 40, 'serving_size_ml' => null,
                'serving_size_units' => '1 sachet',
                'serving_size_description' => '1 sachet (40g)',
                'is_active' => true, 'is_global' => true, 'user_id' => null,
            ],

            // --- DRINK MIXES (Per serving, to be mixed with water) ---
            [
                'name' => 'Tailwind Endurance Fuel (Naked/Unflavored)',
                'brand' => 'Tailwind Nutrition',
                'type' => Product::TYPE_DRINK_MIX,
                'carbs_g' => 25, 'protein_g' => 0, 'fat_g' => 0, // Per scoop/serving
                'sodium_mg' => 310, 'potassium_mg' => 88, 'magnesium_mg' => 14, 'caffeine_mg' => 0,
                'serving_size_g' => 27, // Grams of powder for one serving
                'serving_size_ml' => 500, // Recommended water to mix with
                'serving_size_units' => '1 scoop',
                'serving_size_description' => '1 scoop (27g) for 500-700ml water',
                'notes' => 'Mix one scoop with 500-700ml of water.',
                'is_active' => true, 'is_global' => true, 'user_id' => null,
            ],
            [
                'name' => 'Skratch Labs Sport Hydration Mix (Lemon & Lime)',
                'brand' => 'Skratch Labs',
                'type' => Product::TYPE_DRINK_MIX,
                'carbs_g' => 20, 'protein_g' => 0, 'fat_g' => 0,
                'sodium_mg' => 380, 'potassium_mg' => 38, 'magnesium_mg' => 38, 'caffeine_mg' => 0,
                'serving_size_g' => 22,
                'serving_size_ml' => 500, // Recommended water to mix with
                'serving_size_units' => '1 scoop',
                'serving_size_description' => '1 scoop (22g) for ~500ml water',
                'is_active' => true, 'is_global' => true, 'user_id' => null,
            ],
             [ // Example product used in your logs
                'name' => 'SIS Go Electrolyte (Lemon & Lime)',
                'brand' => 'Science in Sport (SIS)',
                'type' => Product::TYPE_DRINK_MIX,
                'carbs_g' => 36, 'protein_g' => 0, 'fat_g' => 0,
                'sodium_mg' => 240, 'potassium_mg' => 60, 'magnesium_mg' => 5, 'caffeine_mg' => 0,
                'serving_size_g' => 40, // 1 sachet or ~2 scoops
                'serving_size_ml' => 500, // Standard bottle size to mix with
                'serving_size_units' => '1 serving (40g powder)',
                'serving_size_description' => '40g powder for 500ml water',
                'is_active' => true, 'is_global' => true, 'user_id' => null,
            ],


            // --- ENERGY BARS ---
            [
                'name' => 'Clif Bar (Chocolate Chip)',
                'brand' => 'Clif Bar',
                'type' => Product::TYPE_ENERGY_BAR,
                'carbs_g' => 45, 'protein_g' => 9, 'fat_g' => 6,
                'sodium_mg' => 150, 'potassium_mg' => 200, 'magnesium_mg' => 0,'caffeine_mg' => 0,
                'serving_size_g' => 68, 'serving_size_ml' => null,
                'serving_size_units' => '1 bar',
                'serving_size_description' => '1 bar (68g)',
                'is_active' => true, 'is_global' => true, 'user_id' => null,
            ],

            // --- ENERGY CHEWS ---
            [
                'name' => 'Clif Bloks Energy Chews (Strawberry)',
                'brand' => 'Clif Bar',
                'type' => Product::TYPE_ENERGY_CHEW,
                'carbs_g' => 24, 'protein_g' => 0, 'fat_g' => 0, // Per 3 chews / half packet
                'sodium_mg' => 50, 'potassium_mg' => 20, 'magnesium_mg' => 0,'caffeine_mg' => 0, // Some flavors have caffeine
                'serving_size_g' => 30, // 3 chews
                'serving_size_ml' => null,
                'serving_size_units' => '3 chews',
                'serving_size_description' => '3 chews (half packet)',
                'notes' => 'Full packet (6 chews) provides 48g carbs.',
                'is_active' => true, 'is_global' => true, 'user_id' => null,
            ],

            // --- HYDRATION TABLETS ---
            [
                'name' => 'Nuun Sport Hydration Tablet (Lemon Lime)',
                'brand' => 'Nuun',
                'type' => Product::TYPE_HYDRATION_TABLET,
                'carbs_g' => 4, 'protein_g' => 0, 'fat_g' => 0,
                'sodium_mg' => 300, 'potassium_mg' => 150, 'magnesium_mg' => 25, 'caffeine_mg' => 0, // Some variants have caffeine
                'serving_size_g' => 5.5, // Approximate weight of a tablet
                'serving_size_ml' => 475, // Recommended water to dissolve in (approx 16 oz)
                'serving_size_units' => '1 tablet',
                'serving_size_description' => '1 tablet for ~475ml (16oz) water',
                'notes' => 'Primarily for electrolyte replacement, low carb.',
                'is_active' => true, 'is_global' => true, 'user_id' => null,
            ],
        ];

        foreach ($products as $productData) {
            Product::updateOrCreate(
                ['name' => $productData['name'], 'brand' => $productData['brand'], 'user_id' => null], // Unique combination for global products
                $productData
            );
        }

        $this->command->info('Global products seeded successfully!');
    }
}
