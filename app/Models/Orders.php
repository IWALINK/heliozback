<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Orders extends Model
{
    //
    protected $fillable = [
        'user_id',
        'stripe_session_id',
        'status',
        'total_amount',
        'currency',
        'items',
        'payment_method',
        'payment_status',
        'invoice_url',
        'paid_at',
    ];

    protected $casts = [
        'items' => 'array',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
