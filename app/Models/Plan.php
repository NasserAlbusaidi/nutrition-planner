<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Plan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id', // Ensure user_id is fillable
        'name',
        'strava_route_id',
        'strava_route_name',
        'planned_start_time',
        'planned_intensity',
        'estimated_duration_seconds',
        'estimated_avg_power_watts',
        'estimated_total_carbs_g',
        'estimated_total_fluid_ml',
        'estimated_total_sodium_mg',
        'weather_summary',
        'source', // New attribute for source
        'estimated_distance_km', // Optional: If you decide to add this
        'estimated_elevation_m', // Optional: If you decide to add this
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'user_id', // Hide user_id from API responses/JSON
    ];

    protected $casts = [
        'planned_start_time' => 'datetime',
        'estimated_total_carbs_g' => 'float', // Match your DB (decimal(8,2))
        'estimated_total_fluid_ml' => 'float', // Match your DB (decimal(8,2))
        'estimated_total_sodium_mg' => 'float',// Match your DB (decimal(8,2))
    ];

    /**
     * Get the user that owns the plan.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the items associated with the plan.
     */
    public function items() // Renamed from planItems for convention
    {
        return $this->hasMany(PlanItem::class);
    }
}
