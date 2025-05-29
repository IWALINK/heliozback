<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        "full_name",
        "phone_number",
        "address",
        "referral_code",
        "referred_by",
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            $user->referral_code = strtoupper(Str::random(10));
        });
    }
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }



    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    // Relation pour les utilisateurs référés
    public function referrals()
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    // Relation pour le parrain
    public function referee()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function orders()
    {
        return $this->hasMany(Orders::class);
    }
}
