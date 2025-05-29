<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankInformation extends Model
{
    use HasFactory;

    protected $table = 'bank_information';

    protected $fillable = [
        'account_holder_name',
        'bank_name',
        'iban',
        'bic',
        'account_number',
        'routing_number',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
