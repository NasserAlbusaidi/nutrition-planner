<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'weight_kg',
        'ftp_watts',
        'sweat_level',
        'salt_loss_level',
        'strava_user_id',
        'strava_access_token',
        'strava_refresh_token',
        'strava_token_expires_at',

    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'strava_access_token', // Hide tokens from API responses/JSON
        'strava_refresh_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        // *** Ensure this cast is present and correct ***
        'strava_token_expires_at' => 'datetime', // Cast to Carbon instance
         // 'strava_access_token' => 'encrypted', // Requires setup
         // 'strava_refresh_token' => 'encrypted',
    ];


    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.u';
    }

    /**
     * Get the user's plans.
     */

    public function plans() {
        return $this->hasMany(Plan::class);
    }
    /**
     * Get the user's products.
     */
    public function products() {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the user's strava ID
     */
    public function stravaId() {
        return $this->strava_user_id;
    }



}


