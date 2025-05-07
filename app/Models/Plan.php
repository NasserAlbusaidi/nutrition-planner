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
        'source',
        'estimated_distance_km',
        'estimated_elevation_m',
        'hourly_targets_data',
        'recommended_total_carbs_g',
        'recommended_total_fluid_ml',
        'recommended_total_sodium_mg',
        'plan_notes', // Ensure this is fillable if you store warnings here
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
        'estimated_total_carbs_g' => 'float',
        'estimated_total_fluid_ml' => 'float',
        'estimated_total_sodium_mg' => 'float',
        'hourly_targets_data' => 'array',
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
