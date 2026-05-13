<?php
namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

class UserNotification extends DatabaseNotification
{
    protected $table = 'user_notifications';
}
