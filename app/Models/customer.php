<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'customers';

    protected $fillable = [
        'name', 'email', 'password', 'contact'
    ];

    protected $hidden = [
        'password',
    ];
}
