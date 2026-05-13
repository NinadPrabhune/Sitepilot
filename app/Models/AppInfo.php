<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppInfo extends Model
{
    protected $table = 'app_info';

    protected $fillable = [
        'call_us',
        'email_us',
        'whatsapp',
        'version',
        'last_updated',
        'privacy_policy',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'last_updated' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
