<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'carbs_g',
        'sodium_mg',
        'caffeine_mg',
        'serving_size_description',
        'serving_volume_ml',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'user_id', // Hide user_id from API responses/JSON
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }


}
