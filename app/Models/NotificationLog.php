<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $table = 'notification_logs';
    
    protected $fillable = [
        'user_id',
        'event',
        'entity_type',
        'entity_id',
        'sent_at',
    ];
    
    public $timestamps = false;
}
