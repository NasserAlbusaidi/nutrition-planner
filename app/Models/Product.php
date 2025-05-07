<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory; // Ensure HasFactory is imported

class Product extends Model
{
    use HasFactory; // Correct way to use HasFactory

    // Define product types as constants for clarity and consistency
    public const TYPE_DRINK_MIX = 'drink_mix';
    public const TYPE_GEL = 'gel';
    public const TYPE_ENERGY_BAR = 'energy_bar';
    public const TYPE_ENERGY_CHEW = 'energy_chew';
    public const TYPE_REAL_FOOD = 'real_food'; // For fruits, homemade items etc.
    public const TYPE_HYDRATION_TABLET = 'hydration_tablet';
    public const TYPE_RECOVERY_DRINK = 'recovery_drink'; // If you plan to extend to recovery
    public const TYPE_PLAIN_WATER = 'plain_water';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id', // null for global products
        'name',
        'type',    // Use one of the constants above
        'carbs_g',
        'protein_g', // Consider adding protein for recovery or some bars
        'fat_g',     // Consider adding fat for some real foods/bars
        'sodium_mg',
        'potassium_mg', // Common electrolyte
        'magnesium_mg', // Common electrolyte
        'caffeine_mg',
        'serving_size_g',          // Grams for solids/powders
        'serving_size_ml',         // Milliliters for liquids (can be used instead of volume for prepared drinks)
        'serving_size_units',      // e.g., "1 gel", "1 bar", "1 scoop", "1 tablet"
        'serving_size_description',// Free text, e.g., "1 gel (40g)"
        'notes',                   // Any additional notes, e.g., "Mix with 500ml water"
        'brand',                   // Optional: Brand name
        'is_active',               // To enable/disable products
        'is_global',               // To mark products available to all users
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'carbs_g' => 'decimal:2',
        'protein_g' => 'decimal:2',
        'fat_g' => 'decimal:2',
        'sodium_mg' => 'decimal:2',
        'potassium_mg' => 'decimal:2',
        'magnesium_mg' => 'decimal:2',
        'caffeine_mg' => 'decimal:2',
        'serving_size_g' => 'decimal:2',
        'serving_size_ml' => 'decimal:2',
        'is_active' => 'boolean',
        'is_global' => 'boolean',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // 'user_id', // Keep user_id if needed for admins, otherwise hide
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
