<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    protected $fillable = ['user_id', 'session_id', 'token', 'expires_at', "ability"];
    protected $dates = ['expires_at'];
}
